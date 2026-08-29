<?php
declare(strict_types=1);

/**
 * Standalone smoke test (no PHPUnit) - requires `composer install` in
 * php-sdk/ first (for psr/log): php www/dev-clients/php-sdk/tests/smoke.php
 */

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

require __DIR__ . '/../src/Exceptions/SdkException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/Exceptions/AuthException.php';
require __DIR__ . '/../src/Exceptions/RateLimitException.php';
require __DIR__ . '/../src/Exceptions/ApiException.php';
require __DIR__ . '/../src/Http/HttpClientInterface.php';
require __DIR__ . '/../src/Http/CurlHttpClient.php';
require __DIR__ . '/../src/Http/MockHttpClient.php';
require __DIR__ . '/../src/Auth/RequestSigner.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Resources/AbstractResource.php';
require __DIR__ . '/../src/Resources/Leads.php';
require __DIR__ . '/../src/Resources/Customers.php';
require __DIR__ . '/../src/Resources/Contacts.php';
require __DIR__ . '/../src/Resources/Visitors.php';
require __DIR__ . '/../src/Resources/ErrorsResource.php';
require __DIR__ . '/../src/Client.php';
require __DIR__ . '/../src/WebhookHandler.php';

use ZeroAI\Boss\Sdk\Client;
use ZeroAI\Boss\Sdk\Exceptions\ApiException;
use ZeroAI\Boss\Sdk\Exceptions\AuthException;
use ZeroAI\Boss\Sdk\Exceptions\ValidationException;
use ZeroAI\Boss\Sdk\Http\MockHttpClient;
use ZeroAI\Boss\Sdk\WebhookHandler;

$failures = 0;
function check(string $label, bool $cond) : void {
    global $failures;
    echo ($cond ? "PASS" : "FAIL") . " - {$label}\n";
    if (!$cond) { $failures++; }
}

// 1. Config requires an auth credential.
try {
    Client::class;
    new Client([]);
    check('Config rejects missing credentials', false);
} catch (ValidationException $e) {
    check('Config rejects missing credentials', true);
}

// 2. Signed-client request gets signed and dispatched correctly, and a 2xx response is unwrapped to `data`.
$mock = new MockHttpClient();
$mock->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 42, 'name' => 'Jane']]]);
$boss = new Client([
    'client_id' => 'client_abc',
    'client_secret' => 'shh',
    'http_client' => $mock,
]);
$result = $boss->leads()->create(['name' => 'Jane']);
check('Leads::create() unwraps data', ($result['lead']['id'] ?? null) === 42);
check('X-Client-Name header identifies this SDK', ($mock->requests[0]['headers']['X-Client-Name'] ?? null) === 'boss-php-sdk');
check('X-Client-Version header matches Client::VERSION', ($mock->requests[0]['headers']['X-Client-Version'] ?? null) === \ZeroAI\Boss\Sdk\Client::VERSION);
check('Request went to POST /crm/leads', $mock->requests[0]['method'] === 'POST' && str_ends_with($mock->requests[0]['url'], '/crm/leads'));
check('Signed-client headers present', isset($mock->requests[0]['headers']['X-ZeroAI-Signature'], $mock->requests[0]['headers']['X-ZeroAI-Client']));
check('Idempotency-Key auto-generated on write', isset($mock->requests[0]['headers']['Idempotency-Key']) && $mock->requests[0]['headers']['Idempotency-Key'] !== '');
check('Leads::create() forces type=lead - /crm/leads now requires it explicitly, no server default', json_decode((string)$mock->requests[0]['body'], true)['type'] === 'lead');

// 3. Bearer auth path.
$mock2 = new MockHttpClient();
$mock2->queue(200, ['success' => true, 'data' => ['leads' => []]]);
$bossBearer = new Client(['bearer_token' => 'tok_123', 'http_client' => $mock2]);
$bossBearer->leads()->list(['company_id' => 5]);
check('Bearer auth sends Authorization header', ($mock2->requests[0]['headers']['Authorization'] ?? '') === 'Bearer tok_123');
check('GET query string includes filters', str_contains($mock2->requests[0]['url'], 'company_id=5'));

// 4. Error mapping: 401 -> AuthException.
$mock3 = new MockHttpClient();
$mock3->queue(401, ['success' => false, 'error' => ['code' => 'invalid_token', 'message' => 'bad token']]);
$bossErr = new Client(['bearer_token' => 'bad', 'http_client' => $mock3]);
try {
    $bossErr->leads()->list();
    check('401 throws AuthException', false);
} catch (AuthException $e) {
    check('401 throws AuthException', $e->errorCode() === 'invalid_token');
}

// 5. Error mapping: generic 4xx -> ApiException with request_id preserved.
$mock4 = new MockHttpClient();
$mock4->queue(404, ['success' => false, 'error' => ['code' => 'not_found', 'message' => 'nope'], 'meta' => ['request_id' => 'req-9']]);
$bossErr2 = new Client(['bearer_token' => 'x', 'http_client' => $mock4]);
try {
    $bossErr2->leads()->get(999);
    check('404 throws ApiException', false);
} catch (ApiException $e) {
    check('404 throws ApiException with request id', $e->statusCode() === 404 && $e->requestId() === 'req-9');
}

// 6. WebhookHandler verifies a correctly-signed payload and rejects a tampered one.
$secret = 'wh_secret_xyz';
$body = json_encode(['type' => 'lead.created', 'data' => ['id' => 1]]);
$secretHash = hash('sha256', $secret);
$sig = 'hmac-sha256=' . hash_hmac('sha256', $body, $secretHash);
$handler = new WebhookHandler($secret);
$received = null;
$handler->on('lead.created', function (array $payload) use (&$received) { $received = $payload; });
$handler->handle($body, [
    'X-ZeroAI-Signature' => $sig,
    'X-ZeroAI-Timestamp' => (string)time(),
    'X-ZeroAI-Event-Type' => 'lead.created',
]);
check('WebhookHandler dispatches on valid signature', $received !== null && $received['data']['id'] === 1);

try {
    $handler->handle($body, [
        'X-ZeroAI-Signature' => 'hmac-sha256=deadbeef',
        'X-ZeroAI-Timestamp' => (string)time(),
        'X-ZeroAI-Event-Type' => 'lead.created',
    ]);
    check('WebhookHandler rejects bad signature', false);
} catch (ValidationException $e) {
    check('WebhookHandler rejects bad signature', true);
}

// 7. Customers forces type=customer regardless of caller input, and routes through /crm/leads.
$mock5 = new MockHttpClient();
$mock5->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 7, 'type' => 'customer']]]);
$bossCust = new Client(['bearer_token' => 'tok', 'http_client' => $mock5]);
$bossCust->customers()->create(['name' => 'Acme Inc', 'type' => 'lead']);
$sentBody = json_decode((string)$mock5->requests[0]['body'], true);
check('Customers::create() forces type=customer even if caller passes type=lead', $sentBody['type'] === 'customer');
check('Customers::create() posts to /crm/leads', str_ends_with($mock5->requests[0]['url'], '/crm/leads'));

// 8. Leads::convertToCustomer() - one-way only, per user direction ("a customer is a
// customer forever"). There is deliberately no Customers::convertToLead() - the server
// itself rejects that transition (verified live against the sandbox, not mockable here).
$mock6 = new MockHttpClient();
$mock6->queue(200, ['success' => true, 'data' => ['id' => 7, 'updated' => true]]);
$bossConvert = new Client(['bearer_token' => 'tok', 'http_client' => $mock6]);
$bossConvert->leads()->convertToCustomer(7);
check('Leads::convertToCustomer() PATCHes /leads/{id} with type=customer', $mock6->requests[0]['method'] === 'PATCH'
    && str_ends_with($mock6->requests[0]['url'], '/leads/7')
    && json_decode((string)$mock6->requests[0]['body'], true)['type'] === 'customer');
check('Customers has no convertToLead() method - the reverse transition is not offered', !method_exists(\ZeroAI\Boss\Sdk\Resources\Customers::class, 'convertToLead'));

echo "\n" . ($failures === 0 ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");
exit($failures === 0 ? 0 : 1);
