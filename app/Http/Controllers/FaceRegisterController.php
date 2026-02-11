<?php

namespace BookStack\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller as BaseController;
use BookStack\Users\Models\User;

class FaceRegisterController extends BaseController
{
    public function register(Request $request)
    {
        $user = Auth::user();
        $user = $user ? $user->fresh() : User::find(Auth::id());
        $image = $request->file('image');

        if (!$image) {
            return response()->json(['error' => 'No image provided'], 422);
        }

        \Log::info('Face registration attempt', ['user_id' => $user->id ?? 'N/A', 'image_size' => $image->getSize()]);

        try {
            // STEP 1: DETECT
            \Log::info('[Step 1] Detecting face in image', ['user_id' => $user->id]);
            $detectRes = Http::timeout(45)->asMultipart()->post(
                'https://api-us.faceplusplus.com/facepp/v3/detect',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'image_file' => fopen($image->getPathname(), 'r'),
                    'return_landmark' => 1,
                ]
            )->json();

            if (isset($detectRes['error_message'])) {
                \Log::error('[Step 1] Face++ detect error', $detectRes);
                return response()->json(['error' => 'Face++ error: ' . $detectRes['error_message']], 500);
            }

            if (empty($detectRes['faces'])) {
                \Log::warning('[Step 1] No face detected in image');
                return response()->json(['error' => 'No face detected'], 422);
            }

            $faceToken = $detectRes['faces'][0]['face_token'];
            \Log::info('[Step 1] ✅ Face detected', ['user_id' => $user->id]);

            // STEP 2: VERIFY NEW IMAGE MATCHES CURRENT USER (when a face is already registered)
            $existingToken = null;
            if (!empty($user->face_token)) {
                $prefix = 'faceToken::token::';
                $existingToken = str_starts_with($user->face_token, $prefix)
                    ? substr($user->face_token, strlen($prefix))
                    : $user->face_token;

                $verifyRes = Http::timeout(45)->asForm()->post(
                    'https://api-us.faceplusplus.com/facepp/v3/search',
                    [
                        'api_key' => config('services.facepp.key'),
                        'api_secret' => config('services.facepp.secret'),
                        'faceset_token' => config('services.facepp.faceset'),
                        'face_token' => $faceToken,
                        'return_result_count' => 5,
                    ]
                )->json();

                $matched = false;
                $minConfidence = 80;
                if (!empty($verifyRes['results'])) {
                    foreach ($verifyRes['results'] as $result) {
                        $confidence = $result['confidence'] ?? 0;
                        $matchedToken = $result['face_token'] ?? null;
                        if ($confidence >= $minConfidence && $matchedToken === $existingToken) {
                            $matched = true;
                            break;
                        }
                    }
                }

                if (!$matched) {
                    \Log::warning('[Step 2] Face verification failed before update', ['user_id' => $user->id]);
                    return response()->json(['error' => 'Face verification failed'], 401);
                }
            }

            // STEP 3: REMOVE CURRENT USER'S OLD FACE (if any)
            if ($existingToken) {
                \Log::info('[Step 3] Removing old face token for current user', ['user_id' => $user->id]);
                try {
                    Http::timeout(45)->asForm()->post(
                        'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
                        [
                            'api_key' => config('services.facepp.key'),
                            'api_secret' => config('services.facepp.secret'),
                            'faceset_token' => config('services.facepp.faceset'),
                            'face_tokens' => $existingToken,
                        ]
                    )->json();
                } catch (\Exception $e) {
                    \Log::warning('[Step 3] Failed to remove existing face for current user', ['error' => $e->getMessage()]);
                }
            }

            // STEP 4: SEARCH TO CHECK IF FACE BELONGS TO ANOTHER USER
            \Log::info('[Step 4] Searching faceset to check if face already exists', ['user_id' => $user->id]);
            $searchRes = Http::timeout(45)->asForm()->post(
                'https://api-us.faceplusplus.com/facepp/v3/search',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'faceset_token' => config('services.facepp.faceset'),
                    'face_token' => $faceToken,
                    'return_result_count' => 5,
                ]
            )->json();

            if (!empty($searchRes['results'])) {
                $minConfidence = 80;
                foreach ($searchRes['results'] as $result) {
                    $confidence = $result['confidence'] ?? 0;
                    $matchedToken = $result['face_token'] ?? null;
                    if ($confidence < $minConfidence || !$matchedToken) {
                        continue;
                    }

                    $matchedUser = User::where('face_token', 'faceToken::token::' . $matchedToken)->first();
                    if ($matchedUser && $matchedUser->id !== $user->id) {
                        \Log::warning('[Step 3] Face already registered by another user', [
                            'user_id' => $user->id,
                            'matched_user_id' => $matchedUser->id,
                            'confidence' => $confidence,
                        ]);
                        return response()->json([
                            'error' => 'Face already registered to another account.',
                        ], 409);
                    }
                }
            }

            // STEP 5: ADD NEW FACE (no outer_id to avoid COEXISTENCE conflicts)
            \Log::info('[Step 5] Adding new face to faceset', ['user_id' => $user->id]);
            $addRes = Http::timeout(45)->asForm()->post(
                'https://api-us.faceplusplus.com/facepp/v3/faceset/addface',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'faceset_token' => config('services.facepp.faceset'),
                    'face_tokens' => $faceToken,
                ]
            )->json();

            \Log::info('[Step 5] Addface result', ['success' => !isset($addRes['error_message']), 'error' => $addRes['error_message'] ?? null, 'user_id' => $user->id]);

            // ERROR HANDLING & REMEDIATION
            if (isset($addRes['error_message'])) {
                $errorMsg = strtoupper($addRes['error_message']);
                
                if ($errorMsg === 'COEXISTENCE_ARGUMENTS') {
                    \Log::error('[Remediation] COEXISTENCE_ARGUMENTS occurred. Removing token and retrying...', ['user_id' => $user->id]);

                    usleep(2000000); // Wait 2 seconds for propagation

                    try {
                        Http::timeout(45)->asForm()->post(
                            'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
                            [
                                'api_key' => config('services.facepp.key'),
                                'api_secret' => config('services.facepp.secret'),
                                'faceset_token' => config('services.facepp.faceset'),
                                'face_tokens' => $faceToken,
                            ]
                        )->json();
                    } catch (\Exception $e) {
                        \Log::warning('[Remediation] Removeface failed', ['error' => $e->getMessage()]);
                    }

                    $retryAdd = Http::timeout(45)->asForm()->post(
                        'https://api-us.faceplusplus.com/facepp/v3/faceset/addface',
                        [
                            'api_key' => config('services.facepp.key'),
                            'api_secret' => config('services.facepp.secret'),
                            'faceset_token' => config('services.facepp.faceset'),
                            'face_tokens' => $faceToken,
                        ]
                    )->json();

                    if (isset($retryAdd['error_message'])) {
                        \Log::error('[Remediation] Retry with different outer_id also failed', ['error' => $retryAdd['error_message'], 'request_id' => $retryAdd['request_id'] ?? null]);
                        return response()->json([
                            'error' => 'Unable to register face. Please try uploading a different photo or try again later.',
                            'request_id' => $retryAdd['request_id'] ?? 'unknown'
                        ], 500);
                    }
                    
                    // Use the retry result
                    $addRes = $retryAdd;
                    \Log::info('[Remediation] ✅ Retry succeeded after removeface', ['user_id' => $user->id]);
                } else {
                    \Log::error('[Step 3] Fatal error', ['error' => $addRes['error_message'], 'request_id' => $addRes['request_id'] ?? null]);
                    return response()->json(['error' => 'Failed to register face: ' . $addRes['error_message']], 500);
                }
            }

            // STEP 6: SAVE TO DB
            $user->face_token = 'faceToken::token::' . $faceToken;
            $user->save();

            \Log::info('[Step 6] ✅ Face token saved to database', ['user_id' => $user->id]);
            return response()->json(['success' => true, 'message' => 'Face registered successfully']);

        } catch (\Exception $e) {
            \Log::error('Face registration exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        $user = Auth::user();
        $user = $user ? $user->fresh() : User::find(Auth::id());
        $image = $request->file('image');

        if (!$user || empty($user->face_token)) {
            return response()->json(['error' => 'No registered face to delete'], 400);
        }

        if (!$image) {
            return response()->json(['error' => 'No image provided'], 422);
        }

        try {
            // DETECT
            $detectRes = Http::timeout(45)->asMultipart()->post(
                'https://api-us.faceplusplus.com/facepp/v3/detect',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'image_file' => fopen($image->getPathname(), 'r'),
                    'return_landmark' => 0,
                ]
            )->json();

            if (isset($detectRes['error_message'])) {
                return response()->json(['error' => 'Face++ error: ' . $detectRes['error_message']], 500);
            }

            if (empty($detectRes['faces'])) {
                return response()->json(['error' => 'No face detected'], 422);
            }

            $newFaceToken = $detectRes['faces'][0]['face_token'];

            // VERIFY
            $verifyRes = Http::timeout(45)->asForm()->post(
                'https://api-us.faceplusplus.com/facepp/v3/search',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'faceset_token' => config('services.facepp.faceset'),
                    'face_token' => $newFaceToken,
                    'return_result_count' => 5,
                ]
            )->json();

            $existingToken = str_starts_with($user->face_token, 'faceToken::token::')
                ? substr($user->face_token, strlen('faceToken::token::'))
                : $user->face_token;

            $matched = false;
            $minConfidence = 80;
            if (!empty($verifyRes['results'])) {
                foreach ($verifyRes['results'] as $result) {
                    $confidence = $result['confidence'] ?? 0;
                    $matchedToken = $result['face_token'] ?? null;
                    if ($confidence >= $minConfidence && $matchedToken === $existingToken) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                return response()->json(['error' => 'Face verification failed'], 401);
            }

            // REMOVE FROM FACESET
            try {
                Http::timeout(45)->asForm()->post(
                    'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
                    [
                        'api_key' => config('services.facepp.key'),
                        'api_secret' => config('services.facepp.secret'),
                        'faceset_token' => config('services.facepp.faceset'),
                        'face_tokens' => $existingToken,
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to remove face from faceset during delete', ['error' => $e->getMessage()]);
            }

            $user->face_token = null;
            $user->save();

            return response()->json(['success' => true, 'message' => 'Face data deleted']);

        } catch (\Exception $e) {
            \Log::error('Face delete exception: ' . $e->getMessage());
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function verify(Request $request)
    {
        $user = Auth::user();
        $user = $user ? $user->fresh() : User::find(Auth::id());
        $image = $request->file('image');

        if (!$user || empty($user->face_token)) {
            return response()->json(['error' => 'No registered face'], 400);
        }

        if (!$image) {
            return response()->json(['error' => 'No image provided'], 422);
        }

        try {
            $detectRes = Http::timeout(45)->asMultipart()->post(
                'https://api-us.faceplusplus.com/facepp/v3/detect',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'image_file' => fopen($image->getPathname(), 'r'),
                    'return_landmark' => 0,
                ]
            )->json();

            if (isset($detectRes['error_message'])) {
                return response()->json(['error' => 'Face++ error: ' . $detectRes['error_message']], 500);
            }

            if (empty($detectRes['faces'])) {
                return response()->json(['error' => 'No face detected'], 422);
            }

            $faceToken = $detectRes['faces'][0]['face_token'];

            $searchRes = Http::timeout(45)->asForm()->post(
                'https://api-us.faceplusplus.com/facepp/v3/search',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'faceset_token' => config('services.facepp.faceset'),
                    'face_token' => $faceToken,
                    'return_result_count' => 5,
                ]
            )->json();

            $existingToken = str_starts_with($user->face_token, 'faceToken::token::')
                ? substr($user->face_token, strlen('faceToken::token::'))
                : $user->face_token;

            $matched = false;
            $matchedConfidence = 0;
            $minConfidence = 80;
            if (!empty($searchRes['results'])) {
                foreach ($searchRes['results'] as $result) {
                    $confidence = $result['confidence'] ?? 0;
                    $matchedToken = $result['face_token'] ?? null;
                    if ($confidence >= $minConfidence && $matchedToken === $existingToken) {
                        $matched = true;
                        $matchedConfidence = $confidence;
                        break;
                    }
                }
            }

            if (!$matched) {
                return response()->json(['error' => 'Face verification failed'], 401);
            }

            return response()->json(['success' => true, 'confidence' => $matchedConfidence]);

        } catch (\Exception $e) {
            \Log::error('Face verify exception: ' . $e->getMessage());
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
