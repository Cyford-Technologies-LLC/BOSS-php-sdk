<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #115. Wraps core/webhooks - subscribe your server
 * to BOSS events (lead created, payment captured, etc). See also
 * \ZeroAI\Boss\Sdk\WebhookHandler for verifying/parsing the deliveries this
 * receives.
 *
 * IMPORTANT: these routes are auth "token_or_session" - a signed-client
 * (client_id/client_secret) credential CANNOT call them, only a
 * bearer_token-configured Client. Configure a second Client with
 * bearer_token if your app otherwise uses a signed-client credential for
 * leads/customers/etc.
 */
final class Webhooks extends AbstractResource
{
    public function eventTypes(): array
    {
        return $this->client->call('GET', '/webhooks/events');
    }

    public function list(array $query = []): array
    {
        return $this->client->call('GET', '/webhooks/subscriptions', $query);
    }

    public function create(array $data): array
    {
        return $this->client->call('POST', '/webhooks/subscriptions', [], $data);
    }

    public function get(int $id): array
    {
        return $this->client->call('GET', "/webhooks/subscriptions/{$id}");
    }

    public function update(int $id, array $data): array
    {
        return $this->client->call('PUT', "/webhooks/subscriptions/{$id}", [], $data);
    }

    public function delete(int $id): array
    {
        return $this->client->call('DELETE', "/webhooks/subscriptions/{$id}");
    }

    public function rotateSecret(int $id): array
    {
        return $this->client->call('POST', "/webhooks/subscriptions/{$id}/rotate-secret");
    }

    public function deliveries(array $query = []): array
    {
        return $this->client->call('GET', '/webhooks/deliveries', $query);
    }

    public function retryDelivery(int $id): array
    {
        return $this->client->call('POST', "/webhooks/deliveries/{$id}/retry");
    }
}
