<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * Wraps dynamic/financial's invoices/quotes - the generic mechanism any
 * tenant/vertical uses to record a quote, invoice, and payment against the
 * platform's Accounting ledger (posts the ledger + fires the Funnels event
 * server-side). This is how a tenant's own website/app plugs its sales flow
 * into the ERP, same as leads()/sales()/booking() - not an internal-only
 * surface.
 */
final class Financial extends AbstractResource
{
    /** @param array $data company_id (required), title?, contact_id? or contact_email?, quote_id?, notes?, subtotal?, tax_rate?, total?, status?, due_date? */
    public function createInvoice(array $data): array
    {
        return $this->client->call('POST', '/financial/invoices', [], $data);
    }

    public function listInvoices(array $query = []): array
    {
        return $this->client->call('GET', '/financial/invoices', $query);
    }

    public function getInvoice(int $id): array
    {
        return $this->client->call('GET', "/financial/invoices/{$id}");
    }

    /**
     * @param array $data status? (draft|sent|paid|overdue|cancelled), total? (amount
     *     adjustment), contact_email?, party_type?/party_ref_id?/party_display_name?,
     *     payout? {party_type, party_ref_id, display_name, amount, category}.
     *     status=paid posts the Accounting ledger + fires the Funnels event.
     */
    public function updateInvoice(int $id, array $data): array
    {
        return $this->client->call('PATCH', "/financial/invoices/{$id}", [], $data);
    }

    /** @param array $data title?, company_id?, contact_id? or contact_email?, notes?, tax_rate?, valid_until? */
    public function createQuote(array $data): array
    {
        return $this->client->call('POST', '/financial/quotes', [], $data);
    }

    public function listQuotes(array $query = []): array
    {
        return $this->client->call('GET', '/financial/quotes', $query);
    }

    public function getQuote(int $id): array
    {
        return $this->client->call('GET', "/financial/quotes/{$id}");
    }

    /** Idempotent - a quote already converted returns a 409 ApiException. */
    public function convertQuoteToInvoice(int $id): array
    {
        return $this->client->call('POST', "/financial/quotes/{$id}/convert");
    }

    public function listSales(array $query = []): array
    {
        return $this->client->call('GET', '/financial/sales', $query);
    }

    public function listPayouts(array $query = []): array
    {
        return $this->client->call('GET', '/financial/payouts', $query);
    }
}
