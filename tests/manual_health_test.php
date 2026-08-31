<?php
declare(strict_types=1);

// One-off manual verification against a live sandbox - not part of the
// automated smoke suite (that runs against MockHttpClient, no network).
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
require __DIR__ . '/../src/SystemMetricsCollector.php';
require __DIR__ . '/../src/Resources/AbstractResource.php';
require __DIR__ . '/../src/Resources/CreatesRecords.php';
require __DIR__ . '/../src/Resources/Leads.php';
require __DIR__ . '/../src/Resources/Customers.php';
require __DIR__ . '/../src/Resources/Contacts.php';
require __DIR__ . '/../src/Resources/Visitors.php';
require __DIR__ . '/../src/Resources/ErrorsResource.php';
require __DIR__ . '/../src/Resources/Health.php';
require __DIR__ . '/../src/Client.php';

use ZeroAI\Boss\Sdk\Client;

$boss = new Client([
    'client_id' => getenv('BOSS_TEST_CLIENT_ID'),
    'client_secret' => getenv('BOSS_TEST_CLIENT_SECRET'),
    'environment' => 'sandbox',
    'base_url' => getenv('BOSS_TEST_BASE_URL') ?: 'http://127.0.0.1:8090/api/v2',
]);

echo "--- health()->report() ---\n";
try {
    $result = $boss->health()->report();
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n--- health()->latest() ---\n";
try {
    $result = $boss->health()->latest();
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n--- SystemMetricsCollector::collect() raw (what got sent) ---\n";
echo json_encode(\ZeroAI\Boss\Sdk\SystemMetricsCollector::collect(), JSON_PRETTY_PRINT) . "\n";
