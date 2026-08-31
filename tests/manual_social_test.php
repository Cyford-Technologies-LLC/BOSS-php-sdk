<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Exceptions/SdkException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/Exceptions/AuthException.php';
require __DIR__ . '/../src/Exceptions/RateLimitException.php';
require __DIR__ . '/../src/Exceptions/ApiException.php';
require __DIR__ . '/../src/Http/HttpClientInterface.php';
require __DIR__ . '/../src/Http/CurlHttpClient.php';
require __DIR__ . '/../src/Auth/RequestSigner.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/ResourceRecord.php';
require __DIR__ . '/../src/Resources/AbstractResource.php';
require __DIR__ . '/../src/Resources/CreatesRecords.php';
require __DIR__ . '/../src/Client.php';

use ZeroAI\Boss\Sdk\Client;

$boss = new Client([
    'client_id' => getenv('BOSS_TEST_CLIENT_ID'),
    'client_secret' => getenv('BOSS_TEST_CLIENT_SECRET'),
    'environment' => 'sandbox',
    'base_url' => getenv('BOSS_TEST_BASE_URL') ?: 'http://localhost/api/v2',
]);

echo "--- POST /social/facebook/posts (draft, target doesn't exist yet) ---\n";
$postId = null;
try {
    $result = $boss->call('POST', '/social/facebook/posts', [], [
        'target_type' => 'page',
        'target_id' => 999999,
        'message' => 'Test post from BOSS API v2 - never published',
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    $postId = $result['post_id'] ?? null;
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n--- GET /social/facebook/posts ---\n";
try {
    $result = $boss->call('GET', '/social/facebook/posts');
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

if ($postId) {
    echo "\n--- POST /social/facebook/posts/{$postId}/publish (target page doesn't exist, expect failure) ---\n";
    try {
        $result = $boss->call('POST', "/social/facebook/posts/{$postId}/publish");
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (\Throwable $e) {
        echo "ERROR (expected): " . get_class($e) . ": " . $e->getMessage() . "\n";
    }
}

echo "\n--- POST /social/instagram/posts (draft) ---\n";
try {
    $result = $boss->call('POST', '/social/instagram/posts', [], [
        'ig_user_id' => 'test_ig_user_999',
        'message' => 'Test IG post - never published',
        'image_url' => 'https://example.com/test.jpg',
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n--- POST /social/linkedin/posts (draft) ---\n";
try {
    $result = $boss->call('POST', '/social/linkedin/posts', [], [
        'target_type' => 'profile',
        'target_id' => 999999,
        'message' => 'Test LinkedIn post - never published',
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n--- POST /social/pinterest/pins (draft) ---\n";
try {
    $result = $boss->call('POST', '/social/pinterest/pins', [], [
        'board_id' => 999999,
        'description' => 'Test pin - never published',
        'image_url' => 'https://example.com/test.jpg',
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}
