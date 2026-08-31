<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #117. Wraps crm.sales.* - the native sales
 * pipeline/orders system (there is no separate "orders" concept - sales
 * pipeline records are the canonical equivalent).
 */
final class Sales extends AbstractResource
{
    public function list(array $query = []): array
    {
        return $this->client->call('GET', '/crm/sales', $query);
    }

    public function create(array $data): array
    {
        return $this->client->call('POST', '/crm/sales', [], $data);
    }

    public function get(int $id): array
    {
        return $this->client->call('GET', "/crm/sales/{$id}");
    }

    public function update(int $id, array $data): array
    {
        return $this->client->call('PUT', "/crm/sales/{$id}", [], $data);
    }

    /** @param array $data Either ['records' => [[...], ...]] (up to 1000) or a raw CSV via the escape hatch's file-upload path (not supported here - use Client::call() directly for multipart). */
    public function import(array $data): array
    {
        return $this->client->call('POST', '/crm/sales/import', [], $data);
    }
}
