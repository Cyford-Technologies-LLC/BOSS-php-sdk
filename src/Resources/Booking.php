<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #116. Wraps dynamic/booking - generic recurring
 * appointment/ride scheduling despite living under "dynamic" (not tied to
 * any one vertical). create() immediately materializes near-term
 * occurrences; update() cancels future not-started occurrences and
 * rematerializes.
 */
final class Booking extends AbstractResource
{
    public function create(array $data): array
    {
        return $this->client->call('POST', '/booking/schedules', [], $data);
    }

    public function list(array $query = []): array
    {
        return $this->client->call('GET', '/booking/schedules', $query);
    }

    public function get(int $id): array
    {
        return $this->client->call('GET', "/booking/schedules/{$id}");
    }

    public function update(int $id, array $data): array
    {
        return $this->client->call('PATCH', "/booking/schedules/{$id}", [], $data);
    }

    public function cancel(int $id): array
    {
        return $this->client->call('DELETE', "/booking/schedules/{$id}");
    }
}
