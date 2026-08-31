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
require __DIR__ . '/../src/SystemMetricsCollector.php';
require __DIR__ . '/../src/Resources/AbstractResource.php';
require __DIR__ . '/../src/Resources/CreatesRecords.php';
require __DIR__ . '/../src/Client.php';

use ZeroAI\Boss\Sdk\Client;

$boss = new Client([
    'client_id' => getenv('BOSS_TEST_CLIENT_ID'),
    'client_secret' => getenv('BOSS_TEST_CLIENT_SECRET'),
    'environment' => 'sandbox',
    'base_url' => getenv('BOSS_TEST_BASE_URL') ?: 'http://localhost/api/v2',
    'auto_health_report' => true,
    'auto_health_report_interval' => 60,
]);

echo "--- call #1 (routing/route, unrelated to health) - should ALSO piggyback a real health report ---\n";
try {
    $boss->call('GET', '/routing/route', ['distance_miles' => 1, 'duration_minutes' => 1]);
} catch (\Throwable $e) {
    echo "(primary call errored, that's fine for this test - just checking the piggyback) " . $e->getMessage() . "\n";
}

echo "\n--- health()->latest() - confirm a fresh report landed from the piggyback, not from calling report() directly ---\n";
$latest = $boss->health()->latest();
echo json_encode($latest) . "\n";

echo "\n--- call #2 immediately after - interval is 60s, should NOT piggyback again ---\n";
$before = $latest['report']['id'] ?? null;
try {
    $boss->call('GET', '/routing/route', ['distance_miles' => 1, 'duration_minutes' => 1]);
} catch (\Throwable $e) {
}
$after = $boss->health()->latest();
$afterId = $after['report']['id'] ?? null;
echo "before id={$before} after id={$afterId} - " . ($before === $afterId ? "NO new report (correct)" : "UNEXPECTED new report") . "\n";
