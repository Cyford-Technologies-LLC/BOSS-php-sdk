<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk;

use Psr\Log\LoggerInterface;
use ZeroAI\Boss\Sdk\Auth\RequestSigner;
use ZeroAI\Boss\Sdk\Exceptions\ApiException;
use ZeroAI\Boss\Sdk\Exceptions\AuthException;
use ZeroAI\Boss\Sdk\Exceptions\RateLimitException;
use ZeroAI\Boss\Sdk\Exceptions\SdkException;
use ZeroAI\Boss\Sdk\Exceptions\ValidationException;
use ZeroAI\Boss\Sdk\Resources\Agents;
use ZeroAI\Boss\Sdk\Resources\Booking;
use ZeroAI\Boss\Sdk\Resources\Communications;
use ZeroAI\Boss\Sdk\Resources\Contacts;
use ZeroAI\Boss\Sdk\Resources\Customers;
use ZeroAI\Boss\Sdk\Resources\ErrorsResource;
use ZeroAI\Boss\Sdk\Resources\Funnels;
use ZeroAI\Boss\Sdk\Resources\Health;
use ZeroAI\Boss\Sdk\Resources\Leads;
use ZeroAI\Boss\Sdk\Resources\Media;
use ZeroAI\Boss\Sdk\Resources\Payments;
use ZeroAI\Boss\Sdk\Resources\Products;
use ZeroAI\Boss\Sdk\Resources\Routing;
use ZeroAI\Boss\Sdk\Resources\Sales;
use ZeroAI\Boss\Sdk\Resources\Social;
use ZeroAI\Boss\Sdk\Resources\Visitors;
use ZeroAI\Boss\Sdk\Resources\Webhooks;

/**
 * Entry point for the BOSS PHP SDK.
 *
 *   $boss = new Client([
 *       'client_id' => '...',
 *       'client_secret' => '...',
 *   ]);
 *   $lead = $boss->leads()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
 *
 * Every write method group is a thin wrapper over call() - if a route isn't
 * wrapped yet, call it directly:
 *   $boss->call('POST', '/crm/leads', ['name' => 'Jane Doe']);
 */
final class Client
{
    /** Bump alongside the git tag/CHANGELOG entry on every release - sent as X-Client-Version on every request so BOSS can see which SDK version is actually in use (BOSS project 43 feature #113). */
    public const VERSION = '0.2.0';

    private Config $config;

    private Leads $leads;
    private Contacts $contacts;
    private Customers $customers;
    private Visitors $visitors;
    private ErrorsResource $errors;
    private Health $health;
    private Webhooks $webhooks;
    private Booking $booking;
    private Products $products;
    private Sales $sales;
    private Communications $communications;
    private Routing $routing;
    private Funnels $funnels;
    private Agents $agents;
    private Payments $payments;
    private Media $media;
    private Social $social;

    /** Recursion guard - health()->report() itself calls call(), which must not re-trigger the auto-report piggyback check. */
    private bool $autoHealthReportInProgress = false;

    /**
     * True only when auto health reporting is on AND the traffic-piggyback path (checked
     * from call()) should drive it. False when a native scheduler (currently: WordPress's
     * wp_schedule_event) is driving it instead - see initAutoHealthReport(). An integrator
     * never sets this directly; it's derived once, automatically, from the runtime.
     */
    private bool $autoHealthReportViaTraffic = false;

    public function __construct(array $config)
    {
        $this->config = Config::fromArray($config);

        $this->leads = new Leads($this);
        $this->contacts = new Contacts($this);
        $this->customers = new Customers($this);
        $this->visitors = new Visitors($this);
        $this->errors = new ErrorsResource($this);
        $this->health = new Health($this);
        $this->webhooks = new Webhooks($this);
        $this->booking = new Booking($this);
        $this->products = new Products($this);
        $this->sales = new Sales($this);
        $this->communications = new Communications($this);
        $this->routing = new Routing($this);
        $this->funnels = new Funnels($this);
        $this->agents = new Agents($this);
        $this->payments = new Payments($this);
        $this->media = new Media($this);
        $this->social = new Social($this);

        $this->initAutoHealthReport();
    }

    /**
     * BOSS project 43 feature #126 follow-up (user: "make the apps and
     * plugin automatically route to the proper cron, based on platform...
     * do not make end users need to figure out what to do"). One config
     * flag (`auto_health_report`), one behavior from the integrator's point
     * of view - the SDK detects its own runtime and picks the right
     * scheduling mechanism with zero platform-specific code required from
     * whoever embeds it:
     *
     *  - Running inside WordPress (ABSPATH defined, wp_schedule_event
     *    available): registers a real wp_schedule_event() cron - reliable,
     *    native, and doesn't stack a second "check every request" mechanism
     *    on top of wp-cron's own page-load check.
     *  - Anywhere else (plain PHP app, CLI script): falls back to the
     *    traffic-piggyback checked from call() - opportunistic, no cron
     *    access required at all.
     *
     * Turning the flag off also actively unschedules any previously-
     * registered WordPress cron for this same credential, so flipping a
     * settings checkbox off in wp-admin doesn't leave an orphaned schedule
     * behind - no separate "disable" step for an integrator to remember.
     */
    private function initAutoHealthReport(): void
    {
        $isWordPress = self::isWordPressRuntime();

        if (!$this->config->autoHealthReport) {
            if ($isWordPress) {
                $this->unscheduleWordPressHealthCron();
            }
            $this->autoHealthReportViaTraffic = false;
            return;
        }

        if ($isWordPress) {
            $this->scheduleWordPressHealthCron();
            $this->autoHealthReportViaTraffic = false;
        } else {
            $this->autoHealthReportViaTraffic = true;
        }
    }

    private static function isWordPressRuntime(): bool
    {
        return defined('ABSPATH')
            && function_exists('wp_schedule_event')
            && function_exists('wp_next_scheduled')
            && function_exists('add_action')
            && function_exists('add_filter');
    }

    private function autoHealthReportHookName(): string
    {
        $key = $this->config->clientId ?? $this->config->bearerToken ?? 'default';
        return 'boss_sdk_health_report_' . substr(md5((string)$key), 0, 16);
    }

    private function scheduleWordPressHealthCron(): void
    {
        $hook = $this->autoHealthReportHookName();
        $intervalSeconds = $this->config->autoHealthReportIntervalSeconds;
        $recurrence = 'boss_sdk_interval_' . $intervalSeconds;

        add_filter('cron_schedules', static function (array $schedules) use ($recurrence, $intervalSeconds): array {
            if (!isset($schedules[$recurrence])) {
                $schedules[$recurrence] = [
                    'interval' => $intervalSeconds,
                    'display' => 'BOSS SDK health report (' . $intervalSeconds . 's)',
                ];
            }
            return $schedules;
        });

        add_action($hook, function (): void {
            try {
                $this->health()->report();
            } catch (\Throwable $e) {
                $this->config->logger->debug('BOSS SDK wp-cron health report failed', ['error' => $e->getMessage()]);
            }
        });

        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $recurrence, $hook);
        }
    }

    private function unscheduleWordPressHealthCron(): void
    {
        $hook = $this->autoHealthReportHookName();
        if (wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
        }
    }

    public function leads(): Leads
    {
        return $this->leads;
    }

    public function contacts(): Contacts
    {
        return $this->contacts;
    }

    public function customers(): Customers
    {
        return $this->customers;
    }

    public function visitors(): Visitors
    {
        return $this->visitors;
    }

    public function errors(): ErrorsResource
    {
        return $this->errors;
    }

    public function health(): Health
    {
        return $this->health;
    }

    public function webhooks(): Webhooks
    {
        return $this->webhooks;
    }

    public function booking(): Booking
    {
        return $this->booking;
    }

    public function products(): Products
    {
        return $this->products;
    }

    public function sales(): Sales
    {
        return $this->sales;
    }

    public function communications(): Communications
    {
        return $this->communications;
    }

    public function routing(): Routing
    {
        return $this->routing;
    }

    public function funnels(): Funnels
    {
        return $this->funnels;
    }

    public function agents(): Agents
    {
        return $this->agents;
    }

    public function payments(): Payments
    {
        return $this->payments;
    }

    public function media(): Media
    {
        return $this->media;
    }

    public function social(): Social
    {
        return $this->social;
    }

    public function logger(): LoggerInterface
    {
        return $this->config->logger;
    }

    /**
     * Escape hatch: call any v2 route directly, wrapped or not.
     *
     * @param string $path Route path relative to /api/v2, e.g. "/crm/leads" or "/leads/42".
     * @param array $query Query-string params (GET/DELETE) - merged into the URL, never the body.
     * @param array $body JSON body (POST/PUT/PATCH). Ignored for GET/DELETE.
     * @param array $options ['idempotency_key' => string] to override the auto-generated one.
     * @return array The decoded `data` portion of a successful response.
     *
     * @throws AuthException|RateLimitException|ValidationException|ApiException|SdkException
     */
    public function call(string $method, string $path, array $query = [], array $body = [], array $options = []): array
    {
        try {
            return $this->doCall($method, $path, $query, $body, $options);
        } finally {
            $this->maybeAutoReportHealth($path);
        }
    }

    /**
     * The plain-PHP/CLI half of auto health reporting - see
     * initAutoHealthReport() for the WordPress half and the platform
     * detection that picks between them. Only ever active when
     * autoHealthReportViaTraffic is true, which initAutoHealthReport() sets
     * exactly when auto_health_report is on AND the runtime is NOT
     * WordPress. Piggybacks a health report onto whatever traffic/SDK usage
     * the integrator's app already has, once autoHealthReportIntervalSeconds
     * has elapsed since the last one - no cron job required. A local file
     * (not a DB row) tracks the last-sent time, since this must work even
     * for an integrator with no server access beyond PHP itself.
     */
    private function maybeAutoReportHealth(string $path): void
    {
        if (!$this->autoHealthReportViaTraffic || $this->autoHealthReportInProgress) {
            return;
        }
        if ($path === '/system/health-reports') {
            return; // never piggyback on the health report call itself
        }

        $cacheFile = $this->autoHealthReportCacheFile();
        $lastSent = is_file($cacheFile) ? (int)@file_get_contents($cacheFile) : 0;
        if (time() - $lastSent < $this->config->autoHealthReportIntervalSeconds) {
            return;
        }

        $this->autoHealthReportInProgress = true;
        try {
            $this->health()->report();
            @file_put_contents($cacheFile, (string)time());
        } catch (\Throwable $e) {
            // A background health report must never break the caller's real request.
            $this->config->logger->debug('BOSS SDK auto health report failed', ['error' => $e->getMessage()]);
        } finally {
            $this->autoHealthReportInProgress = false;
        }
    }

    private function autoHealthReportCacheFile(): string
    {
        $key = $this->config->clientId ?? $this->config->bearerToken ?? 'default';
        return sys_get_temp_dir() . '/boss_sdk_auto_health_' . md5((string)$key) . '.ts';
    }

    private function doCall(string $method, string $path, array $query = [], array $body = [], array $options = []): array
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');

        // company_id is a body/query field server-side (CrmHandler reads request->input('company_id')
        // or query['company_id']) - there is no dedicated header for it. Apply the default without
        // clobbering a value the caller explicitly passed for this one call.
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($this->config->companyId !== null) {
            if ($isWrite) {
                $body += ['company_id' => $this->config->companyId];
            } else {
                $query += ['company_id' => $this->config->companyId];
            }
        }

        $url = $this->config->baseUrl . $path . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
        $rawBody = $body !== [] || $isWrite ? (string)json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Client-Name' => 'boss-php-sdk',
            'X-Client-Version' => self::VERSION,
        ];

        if ($this->config->bearerToken !== null) {
            $headers += RequestSigner::bearerHeaders($this->config->bearerToken);
        } else {
            $headers += RequestSigner::signedClientHeaders(
                (string)$this->config->clientId,
                (string)$this->config->clientSecret,
                $method,
                $path,
                $query,
                $rawBody
            );
        }

        if ($isWrite) {
            $headers['Idempotency-Key'] = $options['idempotency_key'] ?? self::generateIdempotencyKey();
        }

        $maxAttempts = max(1, (int)$this->config->retryPolicy['max_attempts']);
        $baseDelayMs = (int)$this->config->retryPolicy['base_delay_ms'];

        $lastException = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->logRequest($method, $url, $rawBody);

            try {
                $response = $this->config->httpClient->send($method, $url, $headers, $rawBody === '' ? null : $rawBody);
            } catch (\Throwable $e) {
                $lastException = new SdkException("Transport error calling {$method} {$path}: {$e->getMessage()}", 0, $e);
                $this->sleepBeforeRetry($attempt, $maxAttempts, $baseDelayMs);
                continue;
            }

            $this->logResponse($method, $url, $response['status'], $response['body']);

            if ($response['status'] === 429) {
                $retryAfter = (int)($response['headers']['Retry-After'] ?? 1);
                if ($attempt < $maxAttempts) {
                    sleep(max(1, $retryAfter));
                    continue;
                }
                throw new RateLimitException('Rate limit exceeded and retries exhausted.', $retryAfter);
            }

            return $this->parseResponse($response);
        }

        throw $lastException ?? new SdkException("Request to {$method} {$path} failed after {$maxAttempts} attempts.");
    }

    private function parseResponse(array $response): array
    {
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new SdkException("Non-JSON response (HTTP {$response['status']}): " . substr($response['body'], 0, 500));
        }

        if (!empty($decoded['success'])) {
            return $decoded['data'] ?? [];
        }

        $error = $decoded['error'] ?? [];
        $code = (string)($error['code'] ?? 'unknown_error');
        $message = (string)($error['message'] ?? 'Unknown API error.');
        $details = (array)($error['details'] ?? []);
        $requestId = (string)($decoded['meta']['request_id'] ?? '');

        if ($response['status'] === 401) {
            throw new AuthException($code, $message);
        }
        if ($response['status'] === 422 || $code === 'validation_failed') {
            throw new ValidationException($message, $details);
        }

        throw new ApiException($response['status'], $code, $message, $details, $requestId);
    }

    private function sleepBeforeRetry(int $attempt, int $maxAttempts, int $baseDelayMs): void
    {
        if ($attempt >= $maxAttempts) {
            return;
        }
        usleep($baseDelayMs * 1000 * $attempt);
    }

    private static function generateIdempotencyKey(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function logRequest(string $method, string $url, string $rawBody): void
    {
        if (!$this->config->debug) {
            return;
        }
        $this->config->logger->debug("BOSS SDK request: {$method} {$url}", ['body' => self::redact($rawBody)]);
    }

    private function logResponse(string $method, string $url, int $status, string $body): void
    {
        if (!$this->config->debug) {
            return;
        }
        $this->config->logger->debug("BOSS SDK response: {$method} {$url} -> {$status}", ['body' => self::redact($body)]);
    }

    /** Redacts credential-shaped fields even in debug logs - tokens/secrets must never reach a log sink. */
    private static function redact(string $json): string
    {
        return (string)preg_replace(
            '/("(?:client_secret|bearer_token|api_key|webhook_secret|password|token)"\s*:\s*")[^"]*(")/i',
            '$1[REDACTED]$2',
            $json
        );
    }
}
