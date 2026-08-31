<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #122. Wraps dynamic/routing - distance/fare
 * estimation. Currently only consumed internally by the IRC app, but the
 * route itself is generic (Google Distance Matrix under the hood), not
 * rideshare-specific.
 */
final class Routing extends AbstractResource
{
    public function route(array $query): array
    {
        return $this->client->call('GET', '/routing/route', $query);
    }

    /** @param array $data origin_lat/lng + dest_lat/lng, or distance_miles + duration_minutes; optional vehicle_id, surge. Fare is null when pricing isn't enabled for the tenant. */
    public function estimate(array $data): array
    {
        return $this->client->call('POST', '/routing/estimate', [], $data);
    }

    /** @param array $query distance_miles + duration_minutes. Throws a 422 ApiException when pricing isn't enabled for the tenant. */
    public function fare(array $query): array
    {
        return $this->client->call('GET', '/routing/fare', $query);
    }
}
