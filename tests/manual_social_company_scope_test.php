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

echo "--- targeting a page belonging to a different company (expect 403) ---\n";
try {
    $r = $boss->call('POST', '/social/facebook/posts', [], [
        'target_type' => 'page',
        'target_id' => 2,
        'message' => 'Should be rejected by company scope check',
    ]);
    echo "UNEXPECTED SUCCESS: " . json_encode($r) . "\n";
} catch (\Throwable $e) {
    echo 'ERROR (expected): ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}
