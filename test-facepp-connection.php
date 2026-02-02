<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "🧪 Testing Face++ API Connectivity\n";
echo str_repeat("─", 50) . "\n\n";

$apiKey = env('FACEPP_API_KEY');
$apiSecret = env('FACEPP_API_SECRET');

echo "📍 Testing connection to: api-us.faceplusplus.com\n";
echo "API Key: " . substr($apiKey, 0, 10) . "...\n";
echo "API Secret: " . substr($apiSecret, 0, 10) . "...\n\n";

// Test with a simple API call (no image needed)
echo "⏱️  Sending test request (with 30s timeout)...\n";

try {
    $startTime = microtime(true);
    
    $response = Http::timeout(30)->asForm()->post(
        'https://api-us.faceplusplus.com/facepp/v3/search',
        [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'faceset_token' => env('FACEPP_FACESET_TOKEN'),
            'face_tokens' => 'test123',
        ]
    );
    
    $elapsed = round((microtime(true) - $startTime) * 1000);
    
    echo "\n✅ Response received in {$elapsed}ms\n";
    echo "Status Code: " . $response->status() . "\n";
    echo "Response:\n";
    var_dump($response->json());
    
} catch (\Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Type: " . class_basename($e) . "\n";
    
    // Check if it's timeout
    if (strpos($e->getMessage(), 'timeout') !== false) {
        echo "\n⚠️  TIMEOUT ERROR - Face++ API is not responding\n";
        echo "Possible causes:\n";
        echo "  1. Your internet connection is slow\n";
        echo "  2. Face++ API server is down\n";
        echo "  3. Network/Firewall blocking\n";
        echo "  4. API credentials are invalid\n";
    }
}

echo "\n" . str_repeat("─", 50) . "\n";
exit();
