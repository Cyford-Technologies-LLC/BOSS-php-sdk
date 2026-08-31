<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #121. Wraps dynamic/funnels server-side reporting
 * (event()/list()/entries() side - see the JS SDK for the client-side
 * event() equivalent). NOTE: the funnels integration's own AI_CONTEXT.md
 * admits a FunnelEngine.php referenced in its docs does not exist - verified
 * 2026-08-31 that these 4 routes do respond with real data server-side, but
 * treat "does enrollment/stage-advancement actually fire" as unconfirmed
 * beyond that.
 *
 * enroll() and list() are auth "token_or_session"-only (bearer/session, no
 * signed-client credential) - operator-only actions. event() and entries()
 * accept a signed-client credential.
 */
final class Funnels extends AbstractResource
{
    /** @param array $data Required: event ('lead_created'|'page_visit'|'email_click'|'email_open'|'form_submit'|'booking_event'). */
    public function event(array $data): array
    {
        return $this->client->call('POST', '/funnels/event', [], $data);
    }

    /** @param array $data Required: funnel_id and a way to identify the lead. Bearer/session only. */
    public function enroll(array $data): array
    {
        return $this->client->call('POST', '/funnels/enroll', [], $data);
    }

    /** Bearer/session only. */
    public function list(array $query = []): array
    {
        return $this->client->call('GET', '/funnels', $query);
    }

    public function entries(array $query = []): array
    {
        return $this->client->call('GET', '/funnels/entries', $query);
    }
}
