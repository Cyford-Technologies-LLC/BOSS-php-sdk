<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk;

/**
 * Best-effort local server metrics for the Server Health Monitoring feature
 * (BOSS project 43 feature #126, user: "cpu memory diskspace etc usage").
 * Every value degrades to null rather than throwing when a metric isn't
 * available on the current platform/SAPI (e.g. sys_getloadavg() and
 * /proc/meminfo don't exist on Windows) - a health reporter must never break
 * the app it's embedded in.
 */
final class SystemMetricsCollector
{
    /** @return array<string,mixed> everything HealthResource::report() needs, ready to send as-is. */
    public static function collect(): array
    {
        $data = [
            'hostname' => gethostname() ?: null,
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY . ' ' . php_uname('r'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            'memory_limit' => ini_get('memory_limit') ?: null,
            'max_execution_time' => (int)ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: null,
            'post_max_size' => ini_get('post_max_size') ?: null,
            'timezone' => date_default_timezone_get(),
            'opcache_enabled' => function_exists('opcache_get_status') && (opcache_get_status(false) !== false),
            'extensions' => self::relevantExtensions(),
        ];

        $load = self::loadAverage();
        if ($load !== null) {
            $data['cpu_load_1'] = $load[0];
            $data['cpu_load_5'] = $load[1];
            $data['cpu_load_15'] = $load[2];
        }

        $mem = self::systemMemory();
        if ($mem !== null) {
            $data['memory_total_bytes'] = $mem['total'];
            $data['memory_used_bytes'] = $mem['used'];
            $data['memory_percent'] = $mem['total'] > 0 ? round($mem['used'] / $mem['total'] * 100, 2) : null;
        }

        $disk = self::diskUsage();
        if ($disk !== null) {
            $data['disk_total_bytes'] = $disk['total'];
            $data['disk_used_bytes'] = $disk['used'];
            $data['disk_percent'] = $disk['total'] > 0 ? round($disk['used'] / $disk['total'] * 100, 2) : null;
        }

        return $data;
    }

    /** [1min, 5min, 15min] system load average, or null (e.g. on Windows, where this always returns false). */
    private static function loadAverage(): ?array
    {
        if (!function_exists('sys_getloadavg')) {
            return null;
        }
        $load = @sys_getloadavg();
        return (is_array($load) && count($load) === 3) ? $load : null;
    }

    /** Real system-wide RAM via /proc/meminfo (Linux only) - PHP has no portable API for this. */
    private static function systemMemory(): ?array
    {
        if (!is_readable('/proc/meminfo')) {
            return null;
        }
        $lines = @file('/proc/meminfo');
        if (!$lines) {
            return null;
        }
        $kv = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $kv[$m[1]] = (int)$m[2] * 1024; // kB -> bytes
            }
        }
        if (!isset($kv['MemTotal'])) {
            return null;
        }
        $total = $kv['MemTotal'];
        $available = $kv['MemAvailable'] ?? ($kv['MemFree'] ?? 0);
        return ['total' => $total, 'used' => max(0, $total - $available)];
    }

    /** Disk usage for the given path (default: filesystem root). Works cross-platform via PHP's disk_*_space(). */
    private static function diskUsage(?string $path = null): ?array
    {
        $path = $path ?? (DIRECTORY_SEPARATOR === '\\' ? 'C:' : '/');
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total === false || $free === false) {
            return null;
        }
        return ['total' => (int)$total, 'used' => (int)$total - (int)$free];
    }

    /** Comma-separated flags for extensions a "can my server run this" evaluation typically cares about. */
    private static function relevantExtensions(): string
    {
        $watch = ['curl', 'pdo', 'pdo_mysql', 'openssl', 'mbstring', 'gd', 'intl', 'zip', 'json'];
        $present = array_filter($watch, fn($ext) => extension_loaded($ext));
        return implode(',', $present);
    }
}
