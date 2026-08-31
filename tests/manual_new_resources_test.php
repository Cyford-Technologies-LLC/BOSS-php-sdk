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
require __DIR__ . '/../src/Resources/Leads.php';
require __DIR__ . '/../src/Resources/Customers.php';
require __DIR__ . '/../src/Resources/Contacts.php';
require __DIR__ . '/../src/Resources/Visitors.php';
require __DIR__ . '/../src/Resources/ErrorsResource.php';
require __DIR__ . '/../src/Resources/Health.php';
require __DIR__ . '/../src/Resources/Webhooks.php';
require __DIR__ . '/../src/Resources/Booking.php';
require __DIR__ . '/../src/Resources/Products.php';
require __DIR__ . '/../src/Resources/Sales.php';
require __DIR__ . '/../src/Resources/Communications.php';
require __DIR__ . '/../src/Resources/Routing.php';
require __DIR__ . '/../src/Resources/Funnels.php';
require __DIR__ . '/../src/Client.php';

use ZeroAI\Boss\Sdk\Client;

$base = getenv('BOSS_TEST_BASE_URL') ?: 'http://127.0.0.1/api/v2';

$signed = new Client([
    'client_id' => getenv('BOSS_TEST_CLIENT_ID'),
    'client_secret' => getenv('BOSS_TEST_CLIENT_SECRET'),
    'environment' => 'sandbox',
    'base_url' => $base,
]);
$bearer = new Client([
    'bearer_token' => getenv('BOSS_TEST_BEARER'),
    'environment' => 'sandbox',
    'base_url' => $base,
]);

function attempt(string $label, callable $fn): void
{
    echo "--- {$label} ---\n";
    try {
        $r = $fn();
        echo json_encode($r, JSON_PRETTY_PRINT) . "\n\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n\n";
    }
}

attempt('booking()->create()', fn() => $signed->booking()->create([
    'title' => 'SDK test booking', 'start_time' => date('c', strtotime('+1 day')),
    'recurrence' => 'weekly', 'day_of_week' => (int)date('N'),
]));
attempt('booking()->list()', fn() => $signed->booking()->list());

attempt('products()->create()', fn() => $signed->products()->create(['name' => 'SDK Test Product']));
attempt('products()->list()', fn() => $signed->products()->list());

attempt('sales()->create()', fn() => $signed->sales()->create(['title' => 'SDK Test Sale', 'amount' => 100]));
attempt('sales()->list()', fn() => $signed->sales()->list());

attempt('routing()->fare() (may 422 if pricing disabled)', fn() => $signed->routing()->fare(['distance_miles' => 5, 'duration_minutes' => 12]));

attempt('funnels()->entries()', fn() => $signed->funnels()->entries());

attempt('webhooks()->list() via bearer', fn() => $bearer->webhooks()->list());
attempt('webhooks()->create() via bearer', fn() => $bearer->webhooks()->create([
    'url' => 'https://example.com/webhook-test', 'event_types' => ['lead.created'],
]));

attempt('webhooks()->list() via SIGNED CLIENT (expect 401/403 - not supported)', fn() => $signed->webhooks()->list());
