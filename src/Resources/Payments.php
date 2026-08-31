<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #118. Wraps the `payments` integration
 * (provider-neutral, Stripe first) - saved payment methods, payment
 * intents, refunds.
 *
 * Disambiguation (BOSS project 43 feature #132, resolved 2026-08-31): two
 * genuinely independent payment-intent/refund implementations exist -
 * dynamic/financial.payments.* (bearer/session-only back-office reporting:
 * invoices, quotes, payouts, its own intent/refund calls) and this
 * `payments` integration (irc_app_or_rider_or_signed_or_token - supports a
 * signed-client credential, and is the one with proper payments.read/write
 * scopes). Unlike the leads/chat/accounts-identity pairs the scan also
 * flagged, this one is NOT layered or a false alarm - both really do create/
 * capture/cancel intents and refunds independently, which is a real drift
 * risk worth a platform-level look eventually. For THIS SDK, `payments`
 * (this file) is the correct one to wrap - dynamic/financial is an internal
 * admin/reporting surface, not meant for a customer-facing SDK to move
 * money through.
 *
 * Server-side only - NEVER expose payment-intent creation to a browser/JS
 * SDK directly.
 */
final class Payments extends AbstractResource
{
    public function config(): array
    {
        return $this->client->call('GET', '/payments/config');
    }

    public function transactions(array $query = []): array
    {
        return $this->client->call('GET', '/payments/transactions', $query);
    }

    /** @param array $data e.g. amount_cents, currency, company_id. */
    public function createIntent(array $data): array
    {
        return $this->client->call('POST', '/payments/payment-intents', [], $data);
    }

    public function captureIntent(string $intentId, array $data = []): array
    {
        return $this->client->call('POST', "/payments/payment-intents/{$intentId}/capture", [], $data);
    }

    public function cancelIntent(string $intentId): array
    {
        return $this->client->call('POST', "/payments/payment-intents/{$intentId}/cancel");
    }

    public function listMethods(array $query = []): array
    {
        return $this->client->call('GET', '/payments/methods', $query);
    }

    public function createSetupIntent(array $data = []): array
    {
        return $this->client->call('POST', '/payments/methods/setup-intent', [], $data);
    }

    public function saveMethod(array $data): array
    {
        return $this->client->call('POST', '/payments/methods', [], $data);
    }

    public function setDefaultMethod(string $methodId): array
    {
        return $this->client->call('POST', "/payments/methods/{$methodId}/default");
    }

    public function deleteMethod(string $methodId): array
    {
        return $this->client->call('DELETE', "/payments/methods/{$methodId}");
    }

    /** @param array $data Required: payment_intent_id. Optional: amount (partial refund), company_id. */
    public function createRefund(array $data): array
    {
        return $this->client->call('POST', '/payments/refunds', [], $data);
    }

    public function listRefunds(array $query = []): array
    {
        return $this->client->call('GET', '/payments/refunds', $query);
    }

    public function dashboard(): array
    {
        return $this->client->call('GET', '/payments/dashboard');
    }

    /** Creates a low-risk test payment intent through the selected provider, for integration validation. */
    public function createTestTransaction(array $data = []): array
    {
        return $this->client->call('POST', '/payments/test-transaction', [], $data);
    }
}
