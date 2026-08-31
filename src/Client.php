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
    public const VERSION = '0.1.9';

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
