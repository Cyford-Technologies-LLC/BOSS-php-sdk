<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #118. Wraps the `payments` integration
 * (provider-neutral, Stripe first) - saved payment methods, payment
 * intents, refunds.
 *
 * Disambiguation (BOSS project 43 feature #132, 2026-08-31; updated
 * 2026-09-05 post-BOSS #537): two independent payment-intent/refund
 * implementations exist - dynamic/financial.payments.* and this `payments`
 * integration (irc_app_or_rider_or_signed_or_token - supports a signed-
 * client credential, proper payments.read/write scopes). For actually
 * moving money (creating/capturing/cancelling a Stripe intent, refunds),
 * `payments` (this file) remains the correct one to wrap here.
 *
 * dynamic/financial's invoices/quotes (see Financial.php in this same
 * directory) are a SEPARATE concern - the generic ledger/bookkeeping
 * record of a sale, not a money-movement call - and are very much meant
 * for tenants to use from this SDK: it's how a tenant's own site/app plugs
 * its sales into the platform's Accounting ledger. The original note here
 * calling all of dynamic/financial "internal admin/reporting, not meant
 * for a customer-facing SDK" predates BOSS #537 and was wrong for that
 * half of the surface - corrected by wrapping it in Financial.php.
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
