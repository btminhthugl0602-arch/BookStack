<?php

namespace BookStack\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller as BaseController;
use BookStack\Users\Models\User;

class FaceRegisterController extends BaseController
{
    private const REVERIFY_SESSION_KEY = 'face_reverify';
    private const REVERIFY_TTL_SECONDS = 120;

    public function register(Request $request)
    {
        $this->extendExecutionTime();

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
            $detectRes = $this->faceppRequest('detect', [
                'api_key' => config('services.facepp.key'),
                'api_secret' => config('services.facepp.secret'),
                'image_file' => fopen($image->getPathname(), 'r'),
                'return_landmark' => 1,
            ], true);

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

            // STEP 2: REQUIRE RE-VERIFICATION BEFORE UPDATE (when a face is already registered)
            $existingToken = null;
            if (!empty($user->face_token)) {
                $existingToken = $this->extractStoredFaceToken($user->face_token);
                $reverifyError = $this->ensureRecentReverification($user, 'update');
                if ($reverifyError !== null) {
                    return $reverifyError;
                }
            }

            // STEP 3: REMOVE CURRENT USER'S OLD FACE (if any)
            if ($existingToken) {
                \Log::info('[Step 3] Removing old face token for current user', ['user_id' => $user->id]);
                try {
                    $this->faceppRequest('faceset/removeface', [
                        'api_key' => config('services.facepp.key'),
                        'api_secret' => config('services.facepp.secret'),
                        'faceset_token' => config('services.facepp.faceset'),
                        'face_tokens' => $existingToken,
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('[Step 3] Failed to remove existing face for current user', ['error' => $e->getMessage()]);
                }
            }

            // STEP 4: SEARCH TO CHECK IF FACE BELONGS TO ANOTHER USER
            \Log::info('[Step 4] Searching faceset to check if face already exists', ['user_id' => $user->id]);
            $searchRes = $this->faceppRequest('search', [
                'api_key' => config('services.facepp.key'),
                'api_secret' => config('services.facepp.secret'),
                'faceset_token' => config('services.facepp.faceset'),
                'face_token' => $faceToken,
                'return_result_count' => 5,
            ]);

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
            $addRes = $this->faceppRequest('faceset/addface', [
                'api_key' => config('services.facepp.key'),
                'api_secret' => config('services.facepp.secret'),
                'faceset_token' => config('services.facepp.faceset'),
                'face_tokens' => $faceToken,
            ]);

            \Log::info('[Step 5] Addface result', ['success' => !isset($addRes['error_message']), 'error' => $addRes['error_message'] ?? null, 'user_id' => $user->id]);

            // ERROR HANDLING & REMEDIATION
            if (isset($addRes['error_message'])) {
                $errorMsg = strtoupper($addRes['error_message']);
                
                if ($errorMsg === 'COEXISTENCE_ARGUMENTS') {
                    \Log::error('[Remediation] COEXISTENCE_ARGUMENTS occurred. Removing token and retrying...', ['user_id' => $user->id]);

                    usleep(2000000); // Wait 2 seconds for propagation

                    try {
                        $this->faceppRequest('faceset/removeface', [
                            'api_key' => config('services.facepp.key'),
                            'api_secret' => config('services.facepp.secret'),
                            'faceset_token' => config('services.facepp.faceset'),
                            'face_tokens' => $faceToken,
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('[Remediation] Removeface failed', ['error' => $e->getMessage()]);
                    }

                    $retryAdd = $this->faceppRequest('faceset/addface', [
                        'api_key' => config('services.facepp.key'),
                        'api_secret' => config('services.facepp.secret'),
                        'faceset_token' => config('services.facepp.faceset'),
                        'face_tokens' => $faceToken,
                    ]);

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

        } catch (ConnectionException $e) {
            \Log::warning('Face registration timeout while calling Face++', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Face verification service is temporarily unavailable. Please try again in a moment.'
            ], 503);
        } catch (\Exception $e) {
            \Log::error('Face registration exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server error while registering face data'], 500);
        }
    }

    public function delete(Request $request)
    {
        $this->extendExecutionTime();

        $user = Auth::user();
        $user = $user ? $user->fresh() : User::find(Auth::id());

        if (!$user || empty($user->face_token)) {
            return response()->json(['error' => 'No registered face to delete'], 400);
        }

        $reverifyError = $this->ensureRecentReverification($user, 'delete');
        if ($reverifyError !== null) {
            return $reverifyError;
        }

        try {
            $existingToken = $this->extractStoredFaceToken($user->face_token);

            // REMOVE FROM FACESET
            try {
                $this->faceppRequest('faceset/removeface', [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'faceset_token' => config('services.facepp.faceset'),
                    'face_tokens' => $existingToken,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to remove face from faceset during delete', ['error' => $e->getMessage()]);
            }

            $user->face_token = null;
            $user->save();

            return response()->json(['success' => true, 'message' => 'Face data deleted']);

        } catch (ConnectionException $e) {
            \Log::warning('Face delete timeout while calling Face++', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Face verification service is temporarily unavailable. Please try again in a moment.'
            ], 503);
        } catch (\Exception $e) {
            \Log::error('Face delete exception: ' . $e->getMessage());
            return response()->json(['error' => 'Server error while deleting face data'], 500);
        }
    }

    public function verify(Request $request)
    {
        $this->extendExecutionTime();

        $user = Auth::user();
        $user = $user ? $user->fresh() : User::find(Auth::id());
        $image = $request->file('image');
        $purpose = $request->input('purpose', 'update');

        if (!in_array($purpose, ['update', 'delete'], true)) {
            return response()->json(['error' => 'Invalid verification purpose'], 422);
        }

        if (!$user || empty($user->face_token)) {
            return response()->json(['error' => 'No registered face'], 400);
        }

        if (!$image) {
            return response()->json(['error' => 'No image provided'], 422);
        }

        try {
            $detectRes = $this->faceppRequest('detect', [
                'api_key' => config('services.facepp.key'),
                'api_secret' => config('services.facepp.secret'),
                'image_file' => fopen($image->getPathname(), 'r'),
                'return_landmark' => 0,
            ], true);

            if (isset($detectRes['error_message'])) {
                return response()->json(['error' => 'Face++ error: ' . $detectRes['error_message']], 500);
            }

            if (empty($detectRes['faces'])) {
                return response()->json(['error' => 'No face detected'], 422);
            }

            $faceToken = $detectRes['faces'][0]['face_token'];

            $existingToken = $this->extractStoredFaceToken($user->face_token);
            $compareRes = $this->faceppRequest('compare', [
                'api_key' => config('services.facepp.key'),
                'api_secret' => config('services.facepp.secret'),
                'face_token1' => $faceToken,
                'face_token2' => $existingToken,
            ]);

            if (isset($compareRes['error_message'])) {
                return response()->json(['error' => 'Face++ error: ' . $compareRes['error_message']], 500);
            }

            $matchedConfidence = (float) ($compareRes['confidence'] ?? 0);
            $matched = $matchedConfidence >= $this->minVerifyConfidence();

            if (!$matched) {
                return response()->json([
                    'error' => 'Face verification failed',
                    'confidence' => $matchedConfidence,
                ], 401);
            }

            session([
                self::REVERIFY_SESSION_KEY => [
                    'user_id' => $user->id,
                    'purpose' => $purpose,
                    'verified_at' => time(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'confidence' => $matchedConfidence,
                'purpose' => $purpose,
            ]);

        } catch (ConnectionException $e) {
            \Log::warning('Face verify timeout while calling Face++', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Face verification service is temporarily unavailable. Please try again in a moment.'
            ], 503);
        } catch (\Exception $e) {
            \Log::error('Face verify exception: ' . $e->getMessage());
            return response()->json(['error' => 'Server error while verifying face data'], 500);
        }
    }

    private function extractStoredFaceToken(string $storedToken): string
    {
        $prefix = 'faceToken::token::';

        return str_starts_with($storedToken, $prefix)
            ? substr($storedToken, strlen($prefix))
            : $storedToken;
    }

    private function minVerifyConfidence(): float
    {
        return (float) env('FACEPP_VERIFY_MIN_CONFIDENCE', 75);
    }

    private function ensureRecentReverification(User $user, string $purpose): ?\Illuminate\Http\JsonResponse
    {
        $verification = session(self::REVERIFY_SESSION_KEY);
        $isValid = is_array($verification)
            && (($verification['user_id'] ?? null) === $user->id)
            && (($verification['purpose'] ?? null) === $purpose)
            && (time() - (int) ($verification['verified_at'] ?? 0) <= self::REVERIFY_TTL_SECONDS);

        if (!$isValid) {
            return response()->json([
                'error' => 'Please verify your face again before continuing.',
            ], 403);
        }

        session()->forget(self::REVERIFY_SESSION_KEY);

        return null;
    }

    private function faceppRequest(string $path, array $payload, bool $multipart = false): array
    {
        $timeout = max(10, (int) env('FACEPP_TIMEOUT', 90));
        $connectTimeout = max(5, (int) env('FACEPP_CONNECT_TIMEOUT', 30));
        $attempts = max(1, (int) env('FACEPP_RETRY_ATTEMPTS', 2));
        $sleepMs = max(0, (int) env('FACEPP_RETRY_SLEEP_MS', 800));

        $maxExecutionTime = (int) ini_get('max_execution_time');
        if ($maxExecutionTime > 0) {
            $safeTimeout = max(8, $maxExecutionTime - 8);
            $timeout = min($timeout, $safeTimeout);
            $connectTimeout = min($connectTimeout, max(3, $timeout - 3));
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = Http::connectTimeout($connectTimeout)->timeout($timeout);
                $request = $multipart ? $request->asMultipart() : $request->asForm();

                $response = $request->post($this->faceppUrl($path), $payload);
                $data = $response->json();

                return is_array($data) ? $data : [];
            } catch (ConnectionException $e) {
                $lastError = $e;

                \Log::warning('Face++ connection timeout', [
                    'path' => $path,
                    'attempt' => $attempt,
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $attempts && $sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        throw $lastError ?? new ConnectionException('Face++ request failed');
    }

    private function faceppUrl(string $path): string
    {
        $baseUrl = rtrim((string) env('FACEPP_BASE_URL', 'https://api-us.faceplusplus.com'), '/');
        $path = ltrim($path, '/');

        return $baseUrl . '/facepp/v3/' . $path;
    }

    private function extendExecutionTime(): void
    {
        $targetSeconds = max(60, (int) env('FACE_FLOW_MAX_EXECUTION_TIME', 120));

        if (function_exists('set_time_limit')) {
            @set_time_limit($targetSeconds);
        }

        @ini_set('max_execution_time', (string) $targetSeconds);
    }
}
