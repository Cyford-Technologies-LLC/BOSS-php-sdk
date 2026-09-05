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
require __DIR__ . '/../src/Resources/Financial.php';
require __DIR__ . '/../src/Resources/Communications.php';
require __DIR__ . '/../src/Resources/Routing.php';
require __DIR__ . '/../src/Resources/Funnels.php';
require __DIR__ . '/../src/Resources/Agents.php';
require __DIR__ . '/../src/Resources/Payments.php';
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
check('Leads::create() returns the created record id via array access', ($result['id'] ?? null) === 42);
check('Leads::create() returns the created record id via object access', ($result->id ?? null) === 42);
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
$customerResult = $bossCust->customers()->create(['name' => 'Acme Inc', 'type' => 'lead']);
$sentBody = json_decode((string)$mock5->requests[0]['body'], true);
check('Customers::create() forces type=customer even if caller passes type=lead', $sentBody['type'] === 'customer');
check('Customers::create() posts to /crm/leads', str_ends_with($mock5->requests[0]['url'], '/crm/leads'));
check('Customers::create() returns the created customer id via object access', ($customerResult->id ?? null) === 7);

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

// 9. Health::report() auto-collects metrics and posts them; overrides win over auto-collected values.
$mock7 = new MockHttpClient();
$mock7->queue(200, ['success' => true, 'data' => ['success' => true, 'accepted' => true, 'report_id' => 1]]);
$bossHealth = new Client(['bearer_token' => 'tok', 'http_client' => $mock7]);
$bossHealth->health()->report(['cpu_load_1' => 9.99]);
$healthBody = json_decode((string)$mock7->requests[0]['body'], true);
check('Health::report() posts to /system/health-reports', str_ends_with($mock7->requests[0]['url'], '/system/health-reports'));
check('Health::report() auto-collects php_version', $healthBody['php_version'] === PHP_VERSION);
check('Health::report() lets an override win over the auto-collected value', $healthBody['cpu_load_1'] === 9.99);

// 10. Newly-wrapped resources (BOSS P43 #115-117, #120-122) - each just needs to hit the
// right verb/path; real end-to-end behavior was verified live against the sandbox
// (tests/manual_new_resources_test.php), not repeated here.
$mock8 = new MockHttpClient();
$mock8->queue(200, ['success' => true, 'data' => ['subscription' => ['id' => 1]]]);
$bossWh = new Client(['bearer_token' => 'tok', 'http_client' => $mock8]);
$bossWh->webhooks()->create(['name' => 'x', 'target_url' => 'https://example.com', 'event_types' => ['lead.created']]);
check('Webhooks::create() posts to /webhooks/subscriptions', str_ends_with($mock8->requests[0]['url'], '/webhooks/subscriptions'));

$mock9 = new MockHttpClient();
$mock9->queue(200, ['success' => true, 'data' => ['schedule' => ['id' => 1]]]);
$bossBooking = new Client(['bearer_token' => 'tok', 'http_client' => $mock9]);
$bossBooking->booking()->create(['customer_lead_id' => 1]);
check('Booking::create() posts to /booking/schedules', str_ends_with($mock9->requests[0]['url'], '/booking/schedules'));

$mock10 = new MockHttpClient();
$mock10->queue(200, ['success' => true, 'data' => ['product' => ['id' => 1]]]);
$bossProducts = new Client(['bearer_token' => 'tok', 'http_client' => $mock10]);
$bossProducts->products()->adjustStock(5, ['qty' => -1, 'type' => 'sale']);
check('Products::adjustStock() posts to /inventory/products/{id}/stock', str_ends_with($mock10->requests[0]['url'], '/inventory/products/5/stock'));

$mock11 = new MockHttpClient();
$mock11->queue(200, ['success' => true, 'data' => ['sale' => ['id' => 1]]]);
$bossSales = new Client(['bearer_token' => 'tok', 'http_client' => $mock11]);
$bossSales->sales()->create(['title' => 'x']);
check('Sales::create() posts to /crm/sales', str_ends_with($mock11->requests[0]['url'], '/crm/sales'));

$mockFin1 = new MockHttpClient();
$mockFin1->queue(200, ['success' => true, 'data' => ['invoice' => ['id' => 1]]]);
$bossFin1 = new Client(['bearer_token' => 'tok', 'http_client' => $mockFin1]);
$bossFin1->financial()->createInvoice(['company_id' => 1, 'total' => 10]);
check('Financial::createInvoice() posts to /financial/invoices', str_ends_with($mockFin1->requests[0]['url'], '/financial/invoices'));

$mockFin2 = new MockHttpClient();
$mockFin2->queue(200, ['success' => true, 'data' => ['invoice' => ['id' => 1, 'status' => 'paid']]]);
$bossFin2 = new Client(['bearer_token' => 'tok', 'http_client' => $mockFin2]);
$bossFin2->financial()->updateInvoice(1, ['status' => 'paid']);
check('Financial::updateInvoice() patches /financial/invoices/{id}', str_ends_with($mockFin2->requests[0]['url'], '/financial/invoices/1'));

$mockFin3 = new MockHttpClient();
$mockFin3->queue(200, ['success' => true, 'data' => ['invoice' => ['id' => 2]]]);
$bossFin3 = new Client(['bearer_token' => 'tok', 'http_client' => $mockFin3]);
$bossFin3->financial()->convertQuoteToInvoice(9);
check('Financial::convertQuoteToInvoice() posts to /financial/quotes/{id}/convert', str_ends_with($mockFin3->requests[0]['url'], '/financial/quotes/9/convert'));

$mock12 = new MockHttpClient();
$mock12->queue(200, ['success' => true, 'data' => []]);
$bossComms = new Client(['bearer_token' => 'tok', 'http_client' => $mock12]);
$bossComms->communications()->sendSms(['to' => '+15550001111', 'message' => 'hi']);
check('Communications::sendSms() posts to /communications/sms', str_ends_with($mock12->requests[0]['url'], '/communications/sms'));

$mock13 = new MockHttpClient();
$mock13->queue(200, ['success' => true, 'data' => ['fare' => 12.5]]);
$bossRouting = new Client(['bearer_token' => 'tok', 'http_client' => $mock13]);
$bossRouting->routing()->fare(['distance_miles' => 5, 'duration_minutes' => 12]);
check('Routing::fare() gets /routing/fare', $mock13->requests[0]['method'] === 'GET' && str_contains($mock13->requests[0]['url'], '/routing/fare'));

$mock14 = new MockHttpClient();
$mock14->queue(200, ['success' => true, 'data' => ['entries' => []]]);
$bossFunnels = new Client(['bearer_token' => 'tok', 'http_client' => $mock14]);
$bossFunnels->funnels()->entries();
check('Funnels::entries() gets /funnels/entries', str_ends_with($mock14->requests[0]['url'], '/funnels/entries'));

// 11. Agents/Payments (BOSS P43 #118-119, resolved via #132 disambiguation) - verified
// live against the sandbox (tests/manual_new_resources_test.php).
$mock15 = new MockHttpClient();
$mock15->queue(200, ['success' => true, 'data' => ['reply' => 'pong']]);
$bossAgents = new Client(['bearer_token' => 'tok', 'http_client' => $mock15]);
$bossAgents->agents()->chat(['agent_id' => 1, 'message' => 'hi']);
check('Agents::chat() posts to /crm/agents/chat', str_ends_with($mock15->requests[0]['url'], '/crm/agents/chat'));

$mock16 = new MockHttpClient();
$mock16->queue(200, ['success' => true, 'data' => ['intent' => ['id' => 'pi_1']]]);
$bossPay = new Client(['bearer_token' => 'tok', 'http_client' => $mock16]);
$bossPay->payments()->createIntent(['amount_cents' => 1000, 'currency' => 'usd']);
check('Payments::createIntent() posts to /payments/payment-intents', str_ends_with($mock16->requests[0]['url'], '/payments/payment-intents'));

// 12. Media (BOSS P43 #123) - verified live against the sandbox
// (tests/manual_media_test.php): a real Replicate image was generated.
$mock17 = new MockHttpClient();
$mock17->queue(200, ['success' => true, 'data' => ['image_url' => 'https://replicate.delivery/x.webp']]);
$bossMedia = new Client(['bearer_token' => 'tok', 'http_client' => $mock17]);
$bossMedia->media()->generateImage('a red bicycle');
check('Media::generateImage() posts to /media/images/generate', str_ends_with($mock17->requests[0]['url'], '/media/images/generate'));

// 13. Media video (BOSS P43 #124) - verified live against the sandbox
// (tests/manual_media_video_test.php): real Replicate calls confirmed
// reaching the tenant's own account, plus a real 404 for an unowned prediction.
$mock18 = new MockHttpClient();
$mock18->queue(200, ['success' => true, 'data' => ['prediction_id' => 'p_1', 'status' => 'starting']]);
$bossMediaVideo = new Client(['bearer_token' => 'tok', 'http_client' => $mock18]);
$bossMediaVideo->media()->generateAvatar('https://x/face.jpg', 'https://x/speech.mp3');
check('Media::generateAvatar() posts to /media/video/generate-avatar', str_ends_with($mock18->requests[0]['url'], '/media/video/generate-avatar'));

$mock19 = new MockHttpClient();
$mock19->queue(200, ['success' => true, 'data' => ['status' => 'succeeded']]);
$bossMediaStatus = new Client(['bearer_token' => 'tok', 'http_client' => $mock19]);
$bossMediaStatus->media()->getAvatarStatus('p_1');
check('Media::getAvatarStatus() gets /media/video/generate-avatar/status', $mock19->requests[0]['method'] === 'GET' && str_contains($mock19->requests[0]['url'], '/media/video/generate-avatar/status'));

// 14. Social (BOSS P43 #125) - verified live against the sandbox
// (tests/manual_social_test.php, manual_social_fake_publish_test.php,
// manual_social_company_scope_test.php): real drafts created on all 4
// platforms, a real Facebook Graph API rejection confirmed for a fake page
// token, and cross-company target access confirmed rejected.
$mock20 = new MockHttpClient();
$mock20->queue(201, ['success' => true, 'data' => ['post_id' => 1]]);
$bossSocial = new Client(['bearer_token' => 'tok', 'http_client' => $mock20]);
$bossSocial->social()->facebookCreatePost(['target_type' => 'page', 'target_id' => 1, 'message' => 'hi']);
check('Social::facebookCreatePost() posts to /social/facebook/posts', str_ends_with($mock20->requests[0]['url'], '/social/facebook/posts'));

$mock21 = new MockHttpClient();
$mock21->queue(200, ['success' => true, 'data' => ['post_id' => 1, 'platform_post_id' => 'x']]);
$bossSocial2 = new Client(['bearer_token' => 'tok', 'http_client' => $mock21]);
$bossSocial2->social()->facebookPublishPost(1);
check('Social::facebookPublishPost() posts to /social/facebook/posts/1/publish', str_ends_with($mock21->requests[0]['url'], '/social/facebook/posts/1/publish'));

$mock22 = new MockHttpClient();
$mock22->queue(201, ['success' => true, 'data' => ['post_id' => 1]]);
$bossSocial3 = new Client(['bearer_token' => 'tok', 'http_client' => $mock22]);
$bossSocial3->social()->instagramCreatePost(['ig_user_id' => 'x', 'image_url' => 'https://x/y.jpg']);
check('Social::instagramCreatePost() posts to /social/instagram/posts', str_ends_with($mock22->requests[0]['url'], '/social/instagram/posts'));

$mock23 = new MockHttpClient();
$mock23->queue(201, ['success' => true, 'data' => ['post_id' => 1]]);
$bossSocial4 = new Client(['bearer_token' => 'tok', 'http_client' => $mock23]);
$bossSocial4->social()->linkedinCreatePost(['target_type' => 'profile', 'target_id' => 1, 'message' => 'hi']);
check('Social::linkedinCreatePost() posts to /social/linkedin/posts', str_ends_with($mock23->requests[0]['url'], '/social/linkedin/posts'));

$mock24 = new MockHttpClient();
$mock24->queue(201, ['success' => true, 'data' => ['pin_id' => 1]]);
$bossSocial5 = new Client(['bearer_token' => 'tok', 'http_client' => $mock24]);
$bossSocial5->social()->pinterestCreatePin(['board_id' => 1, 'description' => 'x', 'image_url' => 'https://x/y.jpg']);
check('Social::pinterestCreatePin() posts to /social/pinterest/pins', str_ends_with($mock24->requests[0]['url'], '/social/pinterest/pins'));

// 15. Products::import() (BOSS P43 #112) - verified live against the sandbox
// (tests/manual_inventory_import_test.php): opencart/woocommerce/canonical
// schemas all confirmed importing real products, update-by-opencart_id
// confirmed, and a category assigned via category_name confirmed preserved
// across a later partial update that omits it.
$mock25 = new MockHttpClient();
$mock25->queue(200, ['success' => true, 'data' => ['created' => 1, 'updated' => 0, 'failed' => 0, 'errors' => []]]);
$bossProductsImport = new Client(['bearer_token' => 'tok', 'http_client' => $mock25]);
$bossProductsImport->products()->import('opencart', [['product_id' => 1, 'name' => 'x', 'price' => '1.00']]);
check('Products::import() posts to /inventory/products/import', str_ends_with($mock25->requests[0]['url'], '/inventory/products/import'));
$importBody = json_decode($mock25->requests[0]['body'] ?? '{}', true);
check('Products::import() sends schema in the body', ($importBody['schema'] ?? null) === 'opencart');

// 16. Auto health-report piggyback (BOSS P43 #126 follow-up, user: "make it
// only check when it is set in the sdk settings... n time can be specified
// too, with a default of 1 hour"). Off unless explicitly enabled; when on,
// call() opportunistically sends a health report once the interval has
// elapsed, tracked via a local file so it works with zero server access
// beyond PHP. Each test below uses a unique credential (uniqid) so its
// /tmp cache file can never collide with a previous smoke.php run's.
$mock26 = new MockHttpClient();
$mock26->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 1]]]);
$bossNoAuto = new Client(['bearer_token' => uniqid('smoke-no-auto-', true), 'http_client' => $mock26]);
$bossNoAuto->leads()->create(['name' => 'x']);
check('Auto health report is off by default - no extra request', count($mock26->requests) === 1);

$mock27 = new MockHttpClient();
$mock27->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 1]]]); // the real call
$mock27->queue(200, ['success' => true, 'data' => ['accepted' => true]]);    // the piggybacked report
$bossAuto = new Client(['bearer_token' => uniqid('smoke-auto-on-', true), 'http_client' => $mock27, 'auto_health_report' => true]);
$bossAuto->leads()->create(['name' => 'x']);
check('auto_health_report=true piggybacks a second request', count($mock27->requests) === 2);
check('the piggybacked request posts to /system/health-reports', str_ends_with($mock27->requests[1]['url'], '/system/health-reports'));

$mock28 = new MockHttpClient();
$mock28->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 1]]]); // call 1
$mock28->queue(200, ['success' => true, 'data' => ['accepted' => true]]);    // report (fires once)
$mock28->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 2]]]); // call 2 - no second report, interval not elapsed
$bossAutoTwice = new Client(['bearer_token' => uniqid('smoke-auto-interval-', true), 'http_client' => $mock28, 'auto_health_report' => true]);
$bossAutoTwice->leads()->create(['name' => 'x']);
$bossAutoTwice->leads()->create(['name' => 'y']);
check('Auto health report fires only once across 2 calls within the interval window', count($mock28->requests) === 3);

$mock29 = new MockHttpClient();
$mock29->queue(200, ['success' => true, 'data' => ['accepted' => true]]);
$bossAutoDirect = new Client(['bearer_token' => uniqid('smoke-auto-direct-', true), 'http_client' => $mock29, 'auto_health_report' => true]);
$bossAutoDirect->health()->report();
check('Calling health()->report() directly does not recurse into a second auto report', count($mock29->requests) === 1);

try {
    new Client(['bearer_token' => uniqid('smoke-auto-badinterval-', true), 'auto_health_report' => true, 'auto_health_report_interval' => 30]);
    check('auto_health_report_interval below 60s throws ValidationException', false);
} catch (ValidationException $e) {
    check('auto_health_report_interval below 60s throws ValidationException', true);
}

echo "\n" . ($failures === 0 ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");
exit($failures === 0 ? 0 : 1);
