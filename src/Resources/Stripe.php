<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * Wraps stripe.config.* - lets an integrator connect their Stripe account
 * directly (mode, account id, publishable/secret keys, webhook secret)
 * without a CRM session login. Writes to the same storage the CRM's own
 * Stripe settings page uses (secrets vaulted server-side) - this SDK/API
 * never sees a secret again once saved, only whether one is set.
 */
final class Stripe extends AbstractResource
{
    /** Mode, account id, both publishable keys, and has_test_secret_key/has_live_secret_key/has_webhook_secret booleans. Never an actual secret value. */
    public function getConfig(): array
    {
        return $this->client->call('GET', '/stripe/config');
    }

    /**
     * @param array $data mode (test|live), stripe_account_id (optional),
     *   test_publishable_key, test_secret_key, live_publishable_key,
     *   live_secret_key, webhook_secret. Omit or send blank for a secret
     *   field to leave the existing stored value unchanged.
     */
    public function saveConfig(array $data): array
    {
        return $this->client->call('POST', '/stripe/config', [], $data);
    }
}
