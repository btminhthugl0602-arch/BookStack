<?php

// Script to clean Face++ faceset directly
// This removes ALL faces and resets the faceset completely

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('FACEPP_API_KEY');
$apiSecret = env('FACEPP_API_SECRET');
$facesetToken = env('FACEPP_FACESET_TOKEN');

echo "🔧 Face++ Faceset Cleanup Tool\n";
echo str_repeat("─", 50) . "\n\n";

// Step 1: Get all faces in faceset
echo "📋 Step 1: Getting all faces in faceset...\n";
$response = Http::timeout(30)->asMultipart()->post(
    'https://api-us.faceplusplus.com/facepp/v3/faceset/getdetail',
    [
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'faceset_token' => $facesetToken,
    ]
)->json();

echo "Response: ";
var_dump($response);

$faceTokens = $response['face_tokens'] ?? [];
$outerIds = $response['outer_ids'] ?? [];

echo "\n📊 Current faceset status:\n";
echo "  - Total faces: " . count($faceTokens) . "\n";
echo "  - Outer IDs: " . json_encode($outerIds) . "\n";

if (!empty($faceTokens)) {
    echo "\n🗑️ Step 2: Removing all faces...\n";
    
    $removeResponse = Http::timeout(30)->asMultipart()->post(
        'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
        [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'faceset_token' => $facesetToken,
            'face_tokens' => implode(',', $faceTokens),
        ]
    )->json();
    
    echo "Remove response: ";
    var_dump($removeResponse);
    
    if (isset($removeResponse['face_count'])) {
        echo "\n✅ Removed {$removeResponse['face_count']} faces\n";
    }
} else {
    echo "\n✅ Faceset is already empty\n";
}

// Step 3: Verify faceset is empty
echo "\n🔍 Step 3: Verifying faceset is empty...\n";
sleep(2);

$verifyResponse = Http::timeout(30)->asMultipart()->post(
    'https://api-us.faceplusplus.com/facepp/v3/faceset/getdetail',
    [
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'faceset_token' => $facesetToken,
    ]
)->json();

$finalFaceCount = count($verifyResponse['face_tokens'] ?? []);

if ($finalFaceCount === 0) {
    echo "✅ Faceset is now clean! (" . $finalFaceCount . " faces)\n";
} else {
    echo "⚠️ Warning: Faceset still has " . $finalFaceCount . " faces\n";
    var_dump($verifyResponse);
}

echo "\n" . str_repeat("─", 50) . "\n";
echo "✅ Cleanup complete. You can now try face registration again.\n";
