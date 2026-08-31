<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

use ZeroAI\Boss\Sdk\ResourceRecord;

/**
 * Wraps the "canonical" leads surface per ENDPOINTS.md open question #1.
 *
 * IMPORTANT auth gap (tracked on BOSS project 43 - do not "fix" by guessing a
 * different route, this is the real state of the manifests as of 2026-08-29):
 *   - create()/list() call POST/GET /crm/leads (core/crm manifest), whose auth
 *     policy is signed_public_or_token - works with EITHER a signed-client
 *     credential or a bearer token. This is the primary path a third-party
 *     site integration should use.
 *   - get()/update()/delete() call /leads/{id} (dynamic/leads manifest),
 *     whose auth policy is bearer/session ONLY - a signed-client credential
 *     will get a 401 here. There is currently no get/update/delete route on
 *     /crm/leads and no signed_client authenticator on /leads/{id}. Until one
 *     of those is added server-side, these three methods only work when the
 *     SDK is configured with bearer_token, not client_id/client_secret.
 *   - No bulk-import route exists in either manifest yet (see BOSS tasks
 *     512/513 - CSV import connectors are planned but this is API import,
 *     a separate need). bulkImport() is intentionally not implemented here
 *     rather than pointing at a route that doesn't exist.
 *
 * /crm/leads requires an explicit `type` on create - no server-side default
 * for this route (user direction, 2026-08-29: an external/API caller must
 * say what it means; only the internal CRM path defaults to 'lead'). This
 * class forces type=lead itself so an SDK consumer never has to think about
 * it or risk the 422 that omitting it now causes.
 */
final class Leads extends AbstractResource
{
    use CreatesRecords;

    public function create(array $data): ResourceRecord
    {
        // ['type' => 'lead'] listed first so it always wins over a conflicting key in $data -
        // this resource forces the type, mirroring Customers::create()'s forced 'customer'.
        return $this->createdRecord(
            $this->client->call('POST', '/crm/leads', [], ['type' => 'lead'] + $data),
            'lead'
        );
    }

    public function list(array $filters = []): array
    {
        return $this->client->call('GET', '/crm/leads', $filters);
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

    /**
     * Requires a bearer_token-configured Client. Converts a lead to a customer in place -
     * the SDK/API equivalent of the web UI's "Convert to Customer" button on the leads page
     * (www/includes/integrations/lead-management/pages/leads.php). One-way only: a customer
     * is a customer forever (user direction, 2026-08-29) - the server rejects the reverse
     * transition with 422 customer_conversion_not_allowed, so there is deliberately no
     * Customers::convertToLead().
     */
    public function convertToCustomer(int $id): array
    {
        return $this->client->call('PATCH', "/leads/{$id}", [], ['type' => 'customer']);
    }
}
