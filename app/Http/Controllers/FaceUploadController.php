<?php

namespace BookStack\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controller as BaseController;

class FaceUploadController extends BaseController
{
    /**
     * Register face by uploading image file
     * POST /user/face/upload-register
     */
    public function uploadRegister(Request $request)
    {
        Log::info('Face upload registration attempt', ['user_id' => $request->user()->id]);
        
        try {
            // Validate file
            $request->validate([
                'face_image' => 'required|image|mimes:jpeg,png,jpg|max:5120' // Max 5MB
            ]);
            
            $user = $request->user();
            $file = $request->file('face_image');
            
            Log::info('Face image uploaded', [
                'user_id' => $user->id,
                'file_size' => $file->getSize(),
                'file_name' => $file->getClientOriginalName()
            ]);
            Storage::makeDirectory('face_uploads');
            
            // Get binary image data and convert to base64
            $imageData = file_get_contents($file->getRealPath());
            $imageBase64 = base64_encode($imageData);
            
            // STEP 1: DETECT FACES
            Log::info('[Upload Step 1] Detecting face in uploaded image', ['user_id' => $user->id]);
            $detectRes = Http::timeout(45)->asForm()->post(
                'https://api-us.faceplusplus.com/facepp/v3/detect',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'image_base64' => $imageBase64
                ]
            );
            
            if (!$detectRes->successful()) {
                Log::error('[Upload Step 1] Face detection failed', [
                    'status' => $detectRes->status(),
                    'response' => $detectRes->json(),
                    'user_id' => $user->id
                ]);
                return response()->json(['error' => 'Could not detect face in image'], 400);
            }
            
            $detectData = $detectRes->json();
            if (empty($detectData['faces'])) {
                Log::warning('[Upload Step 1] No face detected in image', ['user_id' => $user->id]);
                return response()->json(['error' => 'No face detected in image. Please try another photo.'], 400);
            }
            
            Log::info('[Upload Step 1] ✅ Face detected', ['user_id' => $user->id]);
            
            // STEP 2: SEARCH FOR EXISTING FACE (prevent duplicate across users)
            Log::info('[Upload Step 2] Searching faceset to check if face already exists', ['user_id' => $user->id]);
            $searchRes = Http::timeout(45)->asForm()->post(
                'https://api-us.faceplusplus.com/facepp/v3/search',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'image_base64' => $imageBase64,
                    'faceset_token' => config('services.facepp.faceset'),
                    'return_result_count' => 5
                ]
            );
            
            if ($searchRes->successful()) {
                $searchData = $searchRes->json();
                if (!empty($searchData['results'])) {
                    $minConfidence = 80;
                    foreach ($searchData['results'] as $result) {
                        $confidence = $result['confidence'] ?? 0;
                        $matchedToken = $result['face_token'] ?? null;
                        if ($confidence < $minConfidence || !$matchedToken) {
                            continue;
                        }

                        $matchedUser = DB::table('users')
                            ->where('face_token', 'faceToken::token::' . $matchedToken)
                            ->first();

                        if ($matchedUser && (int) $matchedUser->id !== (int) $user->id) {
                            Log::warning('[Upload Step 2] Face already registered by another user', [
                                'user_id' => $user->id,
                                'matched_user_id' => $matchedUser->id,
                                'confidence' => $confidence,
                            ]);
                            return response()->json([
                                'error' => 'Face already registered to another account.'
                            ], 409);
                        }
                    }
                }
            }
            
            // STEP 3: REMOVE CURRENT USER'S OLD FACE (if any)
            $existingToken = null;
            if (!empty($user->face_token)) {
                $prefix = 'faceToken::token::';
                $existingToken = str_starts_with($user->face_token, $prefix)
                    ? substr($user->face_token, strlen($prefix))
                    : $user->face_token;
            }

            if ($existingToken) {
                Log::info('[Upload Step 3] Removing old face token for current user', ['user_id' => $user->id]);
                try {
                    Http::timeout(45)->asForm()->post(
                        'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
                        [
                            'api_key' => config('services.facepp.key'),
                            'api_secret' => config('services.facepp.secret'),
                            'faceset_token' => config('services.facepp.faceset'),
                            'face_tokens' => $existingToken
                        ]
                    );
                } catch (\Exception $e) {
                    Log::warning('[Upload Step 3] Failed to remove existing face for current user', ['error' => $e->getMessage()]);
                }
            }

            // STEP 4: ADD NEW FACE (avoid outer_id to prevent COEXISTENCE conflicts)
            Log::info('[Upload Step 4] Adding new face to faceset', [
                'user_id' => $user->id
            ]);

            $faceTokenToAdd = $detectData['faces'][0]['face_token'] ?? null;
            Log::info('[Upload Step 4] Detected face token', ['user_id' => $user->id, 'face_token' => $faceTokenToAdd]);

            // Try adding up to 3 times with small delays, removing the face token between attempts if COEXISTENCE occurs
            $addRes = null;
            $attempts = 3;
            for ($i = 1; $i <= $attempts; $i++) {
                $addRes = Http::timeout(45)->asForm()->post(
                    'https://api-us.faceplusplus.com/facepp/v3/faceset/addface',
                    [
                        'api_key' => config('services.facepp.key'),
                        'api_secret' => config('services.facepp.secret'),
                        'faceset_token' => config('services.facepp.faceset'),
                        'face_tokens' => $faceTokenToAdd
                    ]
                );

                $addDataTmp = $addRes->json();
                if ($addRes->successful() && !empty($addDataTmp['face_tokens'])) {
                    Log::info('[Upload Step 3] Addface succeeded', ['attempt' => $i, 'response' => $addDataTmp, 'user_id' => $user->id]);
                    break;
                }

                // If COEXISTENCE_ARGUMENTS, try removing detected face token and retry
                $errMsg = $addDataTmp['error_message'] ?? $addDataTmp['error'] ?? null;
                Log::warning('[Upload Step 3] Addface failed attempt', ['attempt' => $i, 'error' => $errMsg, 'response' => $addDataTmp, 'user_id' => $user->id]);

                if ($errMsg === 'COEXISTENCE_ARGUMENTS' && $faceTokenToAdd) {
                    try {
                        $rmRes2 = Http::timeout(45)->asForm()->post(
                            'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
                            [
                                'api_key' => config('services.facepp.key'),
                                'api_secret' => config('services.facepp.secret'),
                                'faceset_token' => config('services.facepp.faceset'),
                                'face_tokens' => $faceTokenToAdd
                            ]
                        );
                        Log::info('[Upload Step 3] Removeface after COEXISTENCE', ['status' => $rmRes2->status(), 'response' => $rmRes2->json(), 'user_id' => $user->id]);
                    } catch (\Exception $e) {
                        Log::debug('[Upload Step 3] Removeface after COEXISTENCE exception', ['error' => $e->getMessage()]);
                    }
                }

                // Small delay before retry
                usleep(300000);
            }
            
            Log::info('[Upload Step 3] Addface result', [
                'success' => $addRes->successful(),
                'status' => $addRes->status(),
                'full_response' => $addRes->json(),
                'user_id' => $user->id
            ]);
            
            if (!$addRes->successful()) {
                $errorData = $addRes->json();
                $error = $errorData['error_message'] ?? $errorData['error'] ?? 'Unknown error';
                
                Log::error('[Upload Step 3] Face addface failed', [
                    'error' => $error,
                    'full_response' => $errorData,
                    'user_id' => $user->id
                ]);
                
                // REMEDIATION: If COEXISTENCE_ARGUMENTS, try different format
                if ($error === 'COEXISTENCE_ARGUMENTS') {
                    Log::error('[Remediation] COEXISTENCE_ARGUMENTS occurred. Trying final cleanup + retry...', [
                        'user_id' => $user->id
                    ]);
                    
                    // As a last-resort attempt, try to scan the faceset for conflicting tokens and remove them
                    try {
                        $scanRes = Http::timeout(45)->asForm()->post(
                            'https://api-us.faceplusplus.com/facepp/v3/faceset/getdetail',
                            [
                                'api_key' => config('services.facepp.key'),
                                'api_secret' => config('services.facepp.secret'),
                                'faceset_token' => config('services.facepp.faceset'),
                                'start' => 0,
                                'length' => 1000
                            ]
                        );
                        $scanData = $scanRes->json();
                        $tokensFound = [];
                        if (!empty($scanData['faces'])) {
                            foreach ($scanData['faces'] as $face) {
                                $tokensFound[] = $face['face_token'] ?? null;
                            }
                        }
                        Log::info('[Remediation] Faceset scan completed', ['found' => count($tokensFound), 'user_id' => $user->id]);
                        // Attempt to remove any tokens that look suspicious (match our detected token or belong to user)
                        foreach ($tokensFound as $tkn) {
                            if (!$tkn) continue;
                            if ($tkn === ($detectData['faces'][0]['face_token'] ?? '') || strpos($tkn, $user->id . '_') === 0) {
                                try {
                                    $removeRes = Http::timeout(45)->asForm()->post(
                                        'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
                                        [
                                            'api_key' => config('services.facepp.key'),
                                            'api_secret' => config('services.facepp.secret'),
                                            'faceset_token' => config('services.facepp.faceset'),
                                            'face_tokens' => $tkn
                                        ]
                                    );
                                    Log::info('[Remediation] Removed suspicious token', ['token' => $tkn, 'response' => $removeRes->json(), 'user_id' => $user->id]);
                                } catch (\Exception $e) {
                                    Log::debug('[Remediation] Failed to remove suspicious token', ['token' => $tkn, 'error' => $e->getMessage()]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::debug('[Remediation] Faceset scan failed', ['error' => $e->getMessage()]);
                    }

                    // Retry add one final time
                    $retryAdd = Http::timeout(45)->asForm()->post(
                        'https://api-us.faceplusplus.com/facepp/v3/faceset/addface',
                        [
                            'api_key' => config('services.facepp.key'),
                            'api_secret' => config('services.facepp.secret'),
                            'faceset_token' => config('services.facepp.faceset'),
                            'face_tokens' => $detectData['faces'][0]['face_token']
                        ]
                    );
                    
                    if ($retryAdd->successful()) {
                        Log::info('[Remediation] Retry succeeded with new outer_id', [
                            'new_outer_id' => $uniqueOuterId,
                            'user_id' => $user->id
                        ]);
                        $addRes = $retryAdd;
                    } else {
                        Log::error('[Remediation] Retry with different outer_id also failed', [
                            'error' => $retryAdd->json()['error'] ?? null,
                            'request_id' => $retryAdd->json()['request_id'] ?? null
                        ]);
                        // Persist uploaded image for background retry and return pending to user
                        try {
                            $storeName = 'face_' . $user->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                            $file->storeAs('face_uploads', $storeName);
                            Log::info('[Remediation] Persisted failed upload for background retry', ['file' => $storeName, 'user_id' => $user->id]);
                        } catch (\Exception $e) {
                            Log::error('[Remediation] Failed to persist upload', ['error' => $e->getMessage()]);
                        }
                        return response()->json(['pending' => true, 'message' => 'Đăng ký khuôn mặt đang được xử lý. Nếu không thành công, chúng tôi sẽ thông báo.'], 202);
                    }
                } else {
                    Log::error('[Upload Step 3] Face addface failed', [
                        'error' => $error,
                        'user_id' => $user->id
                    ]);
                    // Persist uploaded image and return pending 202 rather than failing hard
                    try {
                        $storeName = 'face_' . $user->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $file->storeAs('face_uploads', $storeName);
                        Log::info('[Upload Step 3] Persisted failed upload for background retry', ['file' => $storeName, 'user_id' => $user->id]);
                    } catch (\Exception $e) {
                        Log::error('[Upload Step 3] Failed to persist upload', ['error' => $e->getMessage()]);
                    }
                    return response()->json(['pending' => true, 'message' => 'Đăng ký khuôn mặt đang được xử lý. Nếu không thành công, chúng tôi sẽ thông báo.'], 202);
                }
            }
            
            // STEP 4: SAVE FACE TOKEN TO DATABASE
            $addData = $addRes->json();
            $faceToken = $faceTokenToAdd ?? ($addData['face_tokens'] ?? null);
            
            if (!$faceToken) {
                Log::error('[Upload Step 4] No face token in response', ['user_id' => $user->id]);
                // Persist upload for background retry and return pending
                try {
                    $storeName = 'face_' . $user->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs('face_uploads', $storeName);
                    Log::info('[Upload Step 4] Persisted failed upload (no token) for background retry', ['file' => $storeName, 'user_id' => $user->id]);
                } catch (\Exception $e) {
                    Log::error('[Upload Step 4] Failed to persist upload', ['error' => $e->getMessage()]);
                }
                return response()->json(['pending' => true, 'message' => 'Đăng ký khuôn mặt đang được xử lý. Nếu không thành công, chúng tôi sẽ thông báo.'], 202);
            }
            
            Log::info('[Upload Step 4] Saving face token to database', [
                'user_id' => $user->id,
                'face_token' => $faceToken
            ]);
            
            // Format: faceToken::token::{face_token}
            $storageFaceToken = 'faceToken::token::' . $faceToken;
            
            DB::table('users')
                ->where('id', $user->id)
                ->update(['face_token' => $storageFaceToken]);
            
            Log::info('[Upload Step 4] ✅ Face token saved successfully', [
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '✅ Khuôn mặt đã được đăng ký thành công',
                'face_token' => $storageFaceToken
            ]);
            
        } catch (\Exception $e) {
            Log::error('Face upload exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Attempt to persist the uploaded file for background retry if available
            try {
                if (isset($file) && method_exists($file, 'getClientOriginalExtension')) {
                    $storeName = 'face_' . $user->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs('face_uploads', $storeName);
                    Log::info('[Catch] Persisted failed upload for background retry', ['file' => $storeName, 'user_id' => $user->id]);
                }
            } catch (\Exception $e2) {
                Log::error('[Catch] Failed to persist upload', ['error' => $e2->getMessage()]);
            }
            return response()->json(['pending' => true, 'message' => 'Đăng ký khuôn mặt đang được xử lý. Nếu không thành công, chúng tôi sẽ thông báo.'], 202);
        }
    }
}
