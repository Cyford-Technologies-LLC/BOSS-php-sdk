<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/** Wraps crm.contacts.* (/crm/contacts) - full CRUD, signed_public_or_token, no delete route exists server-side. */
final class Contacts extends AbstractResource
{
    public function create(array $data): array
    {
        return $this->client->call('POST', '/crm/contacts', [], $data);
    }

    public function list(array $filters = []): array
    {
        return $this->client->call('GET', '/crm/contacts', $filters);
    }

    public function get(int $id): array
    {
        return $this->client->call('GET', "/crm/contacts/{$id}");
    }

    public function update(int $id, array $data): array
    {
        return $this->client->call('PUT', "/crm/contacts/{$id}", [], $data);
    }
}
