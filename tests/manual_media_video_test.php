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
]);

echo "--- POST /media/video/generate-avatar (raw call()) ---\n";
try {
    $result = $boss->call('POST', '/media/video/generate-avatar', [], [
        'image_url' => 'https://example.com/face.jpg',
        'audio_url' => 'https://example.com/speech.mp3',
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

    if (isset($result['prediction_id'])) {
        echo "\n--- GET /media/video/generate-avatar/status ---\n";
        $status = $boss->call('GET', '/media/video/generate-avatar/status', ['prediction_id' => $result['prediction_id']]);
        echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n--- GET status for an unknown prediction_id (expect 404) ---\n";
try {
    $boss->call('GET', '/media/video/generate-avatar/status', ['prediction_id' => 'does-not-exist']);
} catch (\Throwable $e) {
    echo "ERROR (expected): " . get_class($e) . ": " . $e->getMessage() . "\n";
}
