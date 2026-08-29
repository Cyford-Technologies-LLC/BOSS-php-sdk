<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * Customers are leads rows with type='customer' (BOSS #500). As of
 * 2026-08-29 the leads endpoints were expanded to cover this directly
 * instead of a separate customer API (see BOSS project 43) - this class is
 * a thin, type-forced wrapper over the same routes Leads uses.
 *
 * Same auth split as Leads:
 *   - create()/list() go through /crm/leads (signed_public_or_token) -
 *     works with either a signed-client credential or a bearer token.
 *   - get()/update()/delete() go through /leads/{id}, bearer/session only.
 *
 * One-way conversion only (user direction, 2026-08-29): "if a customer
 * exists it is a customer forever". Leads::convertToCustomer() exists;
 * there is deliberately no convertToLead() here - the server itself
 * rejects that transition with 422 customer_conversion_not_allowed, so
 * this class doesn't offer a method that would only ever fail.
 */
final class Customers extends AbstractResource
{
    public function create(array $data): array
    {
        // ['type' => 'customer'] listed first so it always wins over a conflicting key in
        // $data (PHP array union keeps the left operand's value on key collision) - this
        // resource forces the type, it never lets a caller create a plain lead through it.
        return $this->client->call('POST', '/crm/leads', [], ['type' => 'customer'] + $data);
    }

    public function list(array $filters = []): array
    {
        return $this->client->call('GET', '/crm/leads', ['type' => 'customer'] + $filters);
    }

    /** Requires a bearer_token-configured Client - see class docblock. */
    public function get(int $id): array
    {
        return $this->client->call('GET', "/leads/{$id}");
    }

    /** Requires a bearer_token-configured Client - see class docblock. */
    public function update(int $id, array $data): array
    {
        return $this->client->call('PATCH', "/leads/{$id}", [], $data);
    }

    /** Requires a bearer_token-configured Client - see class docblock. */
    public function delete(int $id): array
    {
        return $this->client->call('DELETE', "/leads/{$id}");
    }
}
