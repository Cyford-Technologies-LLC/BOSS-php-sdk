<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * Wraps ad-pixels' /track/* routes (auth: public_system - no signature, no
 * bearer token; these are meant to be called from a visitor's own browser).
 * Server-side PHP calls through this Client still go through Client::call(),
 * which will attach signed-client/bearer headers the public_system routes
 * simply ignore - harmless, but note the JS SDK's browser-side tracker calls
 * these endpoints directly over fetch/beacon, not through this PHP class.
 * This wrapper exists for server-side use (e.g. backfilling a visitor event
 * from a webhook or a batch job), not as the primary capture path.
 */
final class Visitors extends AbstractResource
{
    public function trackVisitor(array $data): array
    {
        return $this->client->call('POST', '/track/visitor', [], $data);
    }

    public function trackEvent(array $data): array
    {
        return $this->client->call('POST', '/track/visitor-event', [], $data);
    }

    public function identify(array $data): array
    {
        return $this->client->call('POST', '/track/visitor-identity', [], $data);
    }

    /** Binds an already-tracked visitor to a lead (e.g. after they submit a form). */
    public function bindToLead(array $data): array
    {
        return $this->client->call('POST', '/track/visitor-lead', [], $data);
    }
}
