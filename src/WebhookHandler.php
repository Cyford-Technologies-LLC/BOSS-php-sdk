<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk;

use ZeroAI\Boss\Sdk\Exceptions\ValidationException;

/**
 * Verifies and parses inbound BOSS webhook deliveries, then dispatches to a
 * typed handler instead of making the caller parse raw JSON.
 *
 * Mirrors ZeroAI\API\V2\WebhookDeliveryService::deliver() exactly: the
 * signing key is sha256(raw webhook secret), and the signature is
 * "hmac-sha256=" . hash_hmac('sha256', $rawBody, $secretHash) - both sides
 * must match or verification always fails.
 *
 * Usage:
 *   $handler = new WebhookHandler($webhookSecret);
 *   $handler->on('lead.created', fn(array $payload) => ...);
 *   $handler->handle($rawBody, $headers); // throws on bad signature
 */
final class WebhookHandler
{
    private string $secretHash;

    /** @var array<string, list<callable(array):void>> */
    private array $listeners = [];

    /** Deliveries are rejected outside this window to prevent replay of an intercepted request. */
    private int $maxTimestampSkewSeconds = 300;

    public function __construct(string $webhookSecret)
    {
        $this->secretHash = hash('sha256', $webhookSecret);
    }

    /** @param callable(array):void $callback */
    public function on(string $eventType, callable $callback): void
    {
        $this->listeners[$eventType][] = $callback;
    }

    /**
     * @param array<string,string> $headers Case doesn't matter; pass whatever the framework gave you.
     * @return array The decoded payload, after successful verification and dispatch.
     */
    public function handle(string $rawBody, array $headers): array
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        $signature = $headers['x-zeroai-signature'] ?? '';
        $timestamp = $headers['x-zeroai-timestamp'] ?? '';
        $eventType = $headers['x-zeroai-event-type'] ?? '';

        if ($signature === '' || $timestamp === '') {
            throw new ValidationException('Webhook request is missing X-ZeroAI-Signature or X-ZeroAI-Timestamp.');
        }
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > $this->maxTimestampSkewSeconds) {
            throw new ValidationException('Webhook timestamp is missing, malformed, or outside the replay window.');
        }

        $expected = 'hmac-sha256=' . hash_hmac('sha256', $rawBody, $this->secretHash);
        if (!hash_equals($expected, $signature)) {
            throw new ValidationException('Webhook signature does not match - wrong webhook_secret, or the payload was tampered with in transit.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new ValidationException('Webhook body is not valid JSON.');
        }

        foreach ($this->listeners[$eventType] ?? [] as $callback) {
            $callback($payload);
        }
        foreach ($this->listeners['*'] ?? [] as $callback) {
            $callback($payload);
        }

        return $payload;
    }
}
