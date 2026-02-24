<?php

namespace BookStack\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use BookStack\Users\Models\User;
use Illuminate\Routing\Controller as BaseController;

class FaceLoginController extends BaseController
{
    public function login(Request $request)
    {
        $image = $request->file('image');

        if (!$image) {
            return response()->json(['error' => 'No image provided'], 422);
        }

        // Log request for debugging
        \Log::info('Face login attempt', [
            'ip' => $request->ip(),
            'image_size' => $image->getSize(),
        ]);

        try {
            // 1. DETECT FACES (timeout: 45s)
            $detectResponse = Http::timeout(45)->asMultipart()->post(
                'https://api-us.faceplusplus.com/facepp/v3/detect',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'image_file' => fopen($image->getPathname(), 'r'),
                    'return_landmark' => 1,
                ]
            );

            $detect = $detectResponse->json();

            // Check for Face++ API errors
            if (isset($detect['error_message'])) {
                \Log::error('Face++ detect error', $detect);
                return response()->json([
                    'error' => 'Face++ service error: ' . $detect['error_message']
                ], 500);
            }

            if (empty($detect['faces'])) {
                \Log::warning('No faces detected in image');
                return response()->json(['error' => 'No face detected'], 422);
            }

            $faceToken = $detect['faces'][0]['face_token'];
            \Log::info('Face detected', ['faces_count' => count($detect['faces'])]);

            // 2. SEARCH IN FACESET (timeout: 45s)
            $searchResponse = Http::timeout(45)->asMultipart()->post(
                'https://api-us.faceplusplus.com/facepp/v3/search',
                [
                    'api_key' => config('services.facepp.key'),
                    'api_secret' => config('services.facepp.secret'),
                    'faceset_token' => config('services.facepp.faceset'),
                    'face_token' => $faceToken,
                    'return_result_count' => 5,
                ]
            );

            $search = $searchResponse->json();

            // Check for Face++ API errors
            if (isset($search['error_message'])) {
                \Log::error('Face++ search error', $search);
                return response()->json([
                    'error' => 'Face++ service error: ' . $search['error_message']
                ], 500);
            }

            if (empty($search['results'])) {
                \Log::warning('Face not recognized in faceset');
                return response()->json(['error' => 'Face not recognized'], 401);
            }

            \Log::info('Face search results received', [
                'results_count' => count($search['results'] ?? []),
            ]);

            // 3. CHECK CONFIDENCE THRESHOLD & MAP BY FACE TOKEN
            $minConfidence = 80; // Ngưỡng tin cậy tối thiểu (có thể điều chỉnh)
            $matchedUser = null;
            $matchedConfidence = 0;

            foreach (($search['results'] ?? []) as $result) {
                $confidence = $result['confidence'] ?? 0;
                if ($confidence < $minConfidence) {
                    continue;
                }

                $resultFaceToken = $result['face_token'] ?? null;
                if (!$resultFaceToken) {
                    continue;
                }

                $userByToken = User::where('face_token', 'faceToken::token::' . $resultFaceToken)->first();
                if ($userByToken) {
                    $matchedUser = $userByToken;
                    $matchedConfidence = $confidence;
                    break;
                }
            }

            if (!$matchedUser) {
                \Log::warning('No registered face matched with sufficient confidence', [
                    'min_confidence' => $minConfidence,
                ]);
                return response()->json([
                    'error' => 'Face not recognized',
                ], 401);
            }

            Auth::login($matchedUser);
            \Log::info('User logged in via face token match', ['user_id' => $matchedUser->id]);

            return response()->json([
                'success' => true,
                'confidence' => $matchedConfidence,
                'user_id' => $matchedUser->id,
            ]);

        } catch (ConnectionException $e) {
            \Log::warning('Face login timeout while calling Face++', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Face verification service is temporarily unavailable. Please try again in a moment.'
            ], 503);
        } catch (\Exception $e) {
            \Log::error('Face login exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Server error while logging in with face data'
            ], 500);
        }
    }
}
