<?php
declare(strict_types=1);

/**
 * Standalone test for the WordPress-vs-plain-PHP auto-detection in
 * Client::initAutoHealthReport(). Run in its own process (not part of
 * smoke.php) because it defines global WordPress stub functions
 * (wp_schedule_event, add_action, etc.) that would permanently flip
 * Client::isWordPressRuntime() to true for the rest of any process that
 * loads them - smoke.php's own auto-health tests rely on that being false
 * to exercise the plain-PHP traffic-piggyback path.
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
require __DIR__ . '/../src/Client.php';

use ZeroAI\Boss\Sdk\Client;
use ZeroAI\Boss\Sdk\Http\MockHttpClient;

$failures = 0;
function check(string $label, bool $cond): void {
    global $failures;
    echo ($cond ? "PASS" : "FAIL") . " - {$label}\n";
    if (!$cond) { $failures++; }
}

// ── Simulate a WordPress runtime ────────────────────────────────────────────
define('ABSPATH', '/fake/wp/');

$GLOBALS['wp_stub'] = [
    'scheduled' => [],   // hook => [timestamp, recurrence]
    'actions' => [],     // hook => callable[]
    'schedules' => ['hourly' => ['interval' => 3600, 'display' => 'Once Hourly']],
];

function wp_next_scheduled(string $hook) {
    return $GLOBALS['wp_stub']['scheduled'][$hook][0] ?? false;
}
function wp_schedule_event(int $timestamp, string $recurrence, string $hook): void {
    $GLOBALS['wp_stub']['scheduled'][$hook] = [$timestamp, $recurrence];
}
function wp_clear_scheduled_hook(string $hook): void {
    unset($GLOBALS['wp_stub']['scheduled'][$hook]);
}
function add_action(string $hook, callable $cb): void {
    $GLOBALS['wp_stub']['actions'][$hook][] = $cb;
}
function add_filter(string $hook, callable $cb): void {
    if ($hook === 'cron_schedules') {
        $GLOBALS['wp_stub']['schedules'] = $cb($GLOBALS['wp_stub']['schedules']);
    }
}

// ── Test 1: enabling auto_health_report under WordPress registers a real
//    wp_schedule_event() call and a custom interval - and does NOT use the
//    traffic-piggyback path (no extra HTTP request from call()).
$mock1 = new MockHttpClient();
$mock1->queue(200, ['success' => true, 'data' => ['lead' => ['id' => 1]]]);
$bossWp = new Client([
    'bearer_token' => 'wp-test-token-1',
    'http_client' => $mock1,
    'auto_health_report' => true,
    'auto_health_report_interval' => 1800,
]);

$hookNames = array_keys($GLOBALS['wp_stub']['scheduled']);
check('Constructing with auto_health_report=true under WP registers exactly one wp_schedule_event() hook', count($hookNames) === 1);
$hook = $hookNames[0] ?? '';
check('The custom interval (1800s) is registered via the cron_schedules filter', isset($GLOBALS['wp_stub']['schedules']['boss_sdk_interval_1800']) && $GLOBALS['wp_stub']['schedules']['boss_sdk_interval_1800']['interval'] === 1800);
check('The scheduled hook uses the custom recurrence', ($GLOBALS['wp_stub']['scheduled'][$hook][1] ?? null) === 'boss_sdk_interval_1800');
check('An action callback was registered for the hook', !empty($GLOBALS['wp_stub']['actions'][$hook] ?? []));

$bossWp->leads()->create(['name' => 'x']);
check('Under WordPress, call() does NOT piggyback an extra health-report request (wp-cron drives it instead)', count($mock1->requests) === 1);

// ── Test 2: firing the registered wp-cron action actually sends a real
//    (mocked) health report through the SAME Client instance.
$mock1->queue(200, ['success' => true, 'data' => ['accepted' => true]]);
foreach ($GLOBALS['wp_stub']['actions'][$hook] as $cb) {
    $cb();
}
check('Firing the wp-cron action sends a health report', count($mock1->requests) === 2 && str_ends_with($mock1->requests[1]['url'], '/system/health-reports'));

// ── Test 3: re-constructing a Client with the SAME credential does not
//    double-schedule (wp_next_scheduled() guard).
$mock2 = new MockHttpClient();
new Client(['bearer_token' => 'wp-test-token-1', 'http_client' => $mock2, 'auto_health_report' => true]);
check('Re-constructing with the same credential does not schedule a duplicate hook', count($GLOBALS['wp_stub']['scheduled']) === 1);

// ── Test 4: turning auto_health_report off unschedules the WP cron hook.
$mock3 = new MockHttpClient();
new Client(['bearer_token' => 'wp-test-token-1', 'http_client' => $mock3, 'auto_health_report' => false]);
check('Turning auto_health_report off unschedules the WordPress cron hook', !isset($GLOBALS['wp_stub']['scheduled'][$hook]));

echo "\n" . ($failures === 0 ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");
exit($failures === 0 ? 0 : 1);
