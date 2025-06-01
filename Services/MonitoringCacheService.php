<?php

namespace Flute\Modules\Monitoring\Services;

class MonitoringCacheService
{
    public const CACHE_KEY_SERVERS_STATUS = 'monitoring_servers_status';
    public const CACHE_KEY_SERVERS_COUNT = 'monitoring_servers_count';
    public const CACHE_TTL_PERFORMANCE = 600;
    public const CACHE_TTL = 300;
    
    public const CACHE_KEY_DASHBOARD_METRICS = 'monitoring_dashboard_metrics';
    public const CACHE_KEY_SERVER_DISTRIBUTION = 'monitoring_server_distribution';
    public const CACHE_KEY_HOURLY_TRAFFIC = 'monitoring_hourly_traffic';
    public const CACHE_KEY_SERVER_STATS_PREFIX = 'monitoring_server_stats_';
    public const CACHE_KEY_ALL_SERVERS_PREFIX = 'monitoring_all_servers_';
    
    public const CACHE_TTL_DASHBOARD = 300;
    public const CACHE_TTL_DASHBOARD_PERFORMANCE = 600;

    public function get(string $key, callable $updateCallback, ?int $ttl = null)
    {
        if ($ttl === null) {
            $ttl = is_performance() ? self::CACHE_TTL_PERFORMANCE : self::CACHE_TTL;
        }
        return cache()->callback($key, $updateCallback, $ttl);
    }

    public function getRaw(string $key, $default = null)
    {
        return cache()->get($key, $default);
    }

    public function setRaw(string $key, $value, ?int $ttl = null): void
    {
        if ($ttl === null) {
            $ttl = self::CACHE_TTL;
        }
        cache()->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        cache()->delete($key);
    }

    public function getServersStatusCacheKey(): string
    {
        return self::CACHE_KEY_SERVERS_STATUS;
    }

    public function getServersCountCacheKey(): string
    {
        return self::CACHE_KEY_SERVERS_COUNT;
    }

    public function getDefaultTtl(): int
    {
        return is_performance() ? self::CACHE_TTL_PERFORMANCE : self::CACHE_TTL;
    }

    public function getPerformanceTtl(): int
    {
        return self::CACHE_TTL_PERFORMANCE;
    }
    
    public function getDashboardTtl(): int
    {
        return is_performance() ? self::CACHE_TTL_DASHBOARD_PERFORMANCE : self::CACHE_TTL_DASHBOARD;
    }

    public function clearMonitoringCache(): void
    {
        cache()->delete(self::CACHE_KEY_SERVERS_STATUS);
        cache()->delete(self::CACHE_KEY_SERVERS_COUNT);
    }
    
    public function clearDashboardCache(): void
    {
        cache()->delete(self::CACHE_KEY_DASHBOARD_METRICS);
        cache()->delete(self::CACHE_KEY_SERVER_DISTRIBUTION);
    }
}
