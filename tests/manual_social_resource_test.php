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
require __DIR__ . '/../src/Resources/Social.php';
require __DIR__ . '/../src/Client.php';

use ZeroAI\Boss\Sdk\Client;

$boss = new Client([
    'client_id' => getenv('BOSS_TEST_CLIENT_ID'),
    'client_secret' => getenv('BOSS_TEST_CLIENT_SECRET'),
    'environment' => 'sandbox',
    'base_url' => getenv('BOSS_TEST_BASE_URL') ?: 'http://localhost/api/v2',
]);

echo "--- social()->facebookCreatePost() ---\n";
$post = $boss->social()->facebookCreatePost([
    'target_type' => 'page',
    'target_id' => 999999,
    'message' => 'via social() resource - never published',
]);
echo json_encode($post) . "\n";

echo "\n--- social()->facebookListPosts() ---\n";
$list = $boss->social()->facebookListPosts();
echo 'count: ' . count($list['posts'] ?? []) . "\n";

echo "\n--- social()->facebookPublishPost() (expect failure, no real page) ---\n";
try {
    $boss->social()->facebookPublishPost((int)$post['post_id']);
} catch (\Throwable $e) {
    echo 'ERROR (expected): ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}
