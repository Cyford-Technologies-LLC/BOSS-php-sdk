<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

use ZeroAI\Boss\Sdk\SystemMetricsCollector;

/**
 * Server Health Monitoring (BOSS project 43 feature #126, user: "in the php
 * sdk, i also want a option to track server health. cpu memory diskspace
 * etc usage and allow it to be turned on or off"). Requires a signed-client
 * or bearer credential with the system.health.write scope.
 */
final class Health extends AbstractResource
{
    /**
     * Collects local CPU/memory/disk/PHP-environment metrics and sends them.
     * Silently accepted-but-dropped (not an error) server-side if the org has
     * turned monitoring off - safe to call unconditionally on a cron/interval.
     *
     * @param array $overrides Merged over the auto-collected metrics - pass
     *   your own values for anything SystemMetricsCollector can't see from
     *   inside PHP (e.g. a container orchestrator's own CPU% instead of the
     *   host's raw load average).
     */
    public function report(array $overrides = []): array
    {
        $metrics = array_merge(SystemMetricsCollector::collect(), $overrides);
        return $this->client->call('POST', '/system/health-reports', [], $metrics);
    }

    /** Reads back this credential's own most recently accepted report, and whether monitoring is enabled for the org. */
    public function latest(): array
    {
        return $this->client->call('GET', '/system/health-reports/latest');
    }
}
