<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #117. Wraps the inventory integration's native
 * product/stock system. Distinct from the vendored OpenCart integration
 * (includes/integrations/opencart/) - that's a separate plugin-your-own-store
 * system with no v2 API, out of scope for this generic SDK.
 */
final class Products extends AbstractResource
{
    public function list(array $query = []): array
    {
        return $this->client->call('GET', '/inventory/products', $query);
    }

    public function create(array $data): array
    {
        return $this->client->call('POST', '/inventory/products', [], $data);
    }

    public function get(int $id): array
    {
        return $this->client->call('GET', "/inventory/products/{$id}");
    }

    public function update(int $id, array $data): array
    {
        return $this->client->call('PATCH', "/inventory/products/{$id}", [], $data);
    }

    /** @param array $data Required: qty (positive adds, negative removes), type (receive|sale|adjustment|return|write_off). */
    public function adjustStock(int $id, array $data): array
    {
        return $this->client->call('POST', "/inventory/products/{$id}/stock", [], $data);
    }

    public function listCategories(array $query = []): array
    {
        return $this->client->call('GET', '/inventory/categories', $query);
    }

    /** @param array $data Required: name, slug. Creates, or updates in place if slug already exists. */
    public function createCategory(array $data): array
    {
        return $this->client->call('POST', '/inventory/categories', [], $data);
    }
}
