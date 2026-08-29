<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ZeroAI\Boss\Sdk\Exceptions\ValidationException;
use ZeroAI\Boss\Sdk\Http\HttpClientInterface;
use ZeroAI\Boss\Sdk\Http\CurlHttpClient;

/**
 * Resolved SDK configuration. Build via Config::fromArray() rather than the
 * constructor directly so defaults and validation stay in one place.
 */
final class Config
{
    public string $environment;
    public string $baseUrl;

    /** Bearer token for server-to-server calls. Mutually exclusive with client_id/client_secret. */
    public ?string $bearerToken;

    /** Signed-client credential (matches api_v2_clients). */
    public ?string $clientId;
    public ?string $clientSecret;

    /** Default company/tenant scoping for multi-company accounts. */
    public ?string $companyId;

    /** Verifies inbound webhook signatures. Never sent on outbound requests. */
    public ?string $webhookSecret;

    public int $timeoutMs;

    /** ['max_attempts' => int, 'base_delay_ms' => int] - applies to network errors and 429s only. */
    public array $retryPolicy;

    public LoggerInterface $logger;
    public HttpClientInterface $httpClient;

    /** Verbose request/response logging. Credentials are always redacted, even here. */
    public bool $debug;

    private function __construct()
    {
    }

    public static function fromArray(array $config): self
    {
        $self = new self();

        $self->environment = $config['environment'] ?? 'production';
        if (!in_array($self->environment, ['production', 'sandbox'], true)) {
            throw new ValidationException("environment must be 'production' or 'sandbox', got '{$self->environment}'.");
        }

        if (isset($config['base_url']) && $config['base_url'] !== '') {
            $self->baseUrl = rtrim((string)$config['base_url'], '/');
        } elseif ($self->environment === 'production') {
            $self->baseUrl = 'https://zeroaiboss.com/api/v2';
        } else {
            throw new ValidationException("base_url is required when environment is 'sandbox' — there is no fixed sandbox host, since sandboxes are per-deployment.");
        }

        $self->bearerToken = $config['bearer_token'] ?? null;
        $self->clientId = $config['client_id'] ?? null;
        $self->clientSecret = $config['client_secret'] ?? null;

        if ($self->bearerToken === null && ($self->clientId === null || $self->clientSecret === null)) {
            throw new ValidationException('Provide either bearer_token, or both client_id and client_secret.');
        }

        $self->companyId = isset($config['company_id']) ? (string)$config['company_id'] : null;
        $self->webhookSecret = $config['webhook_secret'] ?? null;
        $self->timeoutMs = (int)($config['timeout_ms'] ?? 10000);

        $self->retryPolicy = array_merge([
            'max_attempts' => 3,
            'base_delay_ms' => 250,
        ], $config['retry_policy'] ?? []);

        $self->logger = $config['logger'] ?? new NullLogger();
        $self->httpClient = $config['http_client'] ?? new CurlHttpClient($self->timeoutMs);
        $self->debug = (bool)($config['debug'] ?? false);

        return $self;
    }
}
