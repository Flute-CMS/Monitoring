<?php

namespace Flute\Modules\Monitoring\Services;

use Flute\Admin\Packages\Dashboard\Services\DashboardService;
use Flute\Admin\Platform\Fields\Tab;
use Flute\Admin\Platform\Layouts\Chart;
use Flute\Admin\Platform\Layouts\LayoutFactory;
use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\Services\MonitoringCacheService;

/**
 * Service to handle monitoring dashboard functionality
 */
class MonitoringDashboardService
{
    /**
     * @var MonitoringService
     */
    protected MonitoringService $monitoringService;

    /**
     * @var MonitoringCacheService
     */
    protected MonitoringCacheService $cacheService;

    /**
     * @var MonitoringStatsService
     */
    protected MonitoringStatsService $statsService;

    /**
     * Constructor
     */
    public function __construct(
        MonitoringService $monitoringService,
        MonitoringStatsService $statsService,
        MonitoringCacheService $cacheService
    ) {
        $this->monitoringService = $monitoringService;
        $this->statsService = $statsService;
        $this->cacheService = $cacheService;
    }

    /**
     * Register the monitoring statistics tab to the dashboard
     */
    public function registerDashboardTab(DashboardService $dashboard): void
    {
        // Was made for optimization purposes
        if (request()->input('tab-dashboard_tabs') !== \Illuminate\Support\Str::slug(__('monitoring.tabs.statistics'))) {
            $mainTab = Tab::make(__('monitoring.tabs.statistics'))
                ->icon('ph.regular.chart-line')
                ->layouts([]);

            $dashboard->addTab($mainTab);

            return;
        }

        $cacheKey = MonitoringCacheService::CACHE_KEY_DASHBOARD_METRICS;
        $metrics = $this->cacheService->get($cacheKey, function () {
            return $this->statsService->calculateMonitoringMetrics();
        }, $this->cacheService->getDashboardTtl());

        $metricsLayout = $this->createMetricsLayout();

        $serverDistributionChart = $this->createServerDistributionChart();
        $hourlyTrafficChart = $this->createHourlyTrafficChart();

        $rightSideLayout = LayoutFactory::blank([
            $serverDistributionChart,
            $hourlyTrafficChart
        ]);

        $servers = Server::findAll(['enabled' => true]);
        $contentLayout = $this->createContentLayout($servers, $rightSideLayout);

        $mainTab = Tab::make(__('monitoring.tabs.statistics'))
            ->icon('ph.regular.chart-line')
            ->layouts([
                $metricsLayout,
                $contentLayout
            ]);

        $dashboard->addTab($mainTab, $metrics);
    }

    /**
     * Create metrics layout for the dashboard
     */
    protected function createMetricsLayout()
    {
        return LayoutFactory::metrics([
            'monitoring.metrics.total_servers' => 'vars.total_servers',
            'monitoring.metrics.online_servers' => 'vars.online_servers',
            'monitoring.metrics.total_players' => 'vars.total_players',
            'monitoring.metrics.servers_fill' => 'vars.servers_fill',
        ])->setIcons([
            'monitoring.metrics.total_servers' => 'database',
            'monitoring.metrics.online_servers' => 'database',
            'monitoring.metrics.total_players' => 'users',
            'monitoring.metrics.servers_fill' => 'chart-line-up',
        ]);
    }

    /**
     * Create server distribution chart
     */
    protected function createServerDistributionChart()
    {
        $cacheKey = MonitoringCacheService::CACHE_KEY_SERVER_DISTRIBUTION;
        $serverDistribution = $this->cacheService->get($cacheKey, function () {
            return $this->statsService->getServerDistribution();
        }, $this->cacheService->getDashboardTtl());

        return Chart::make('server_distribution', __('monitoring.charts.server_distribution'))
            ->type('donut')
            ->height(200)
            ->description(__('monitoring.descriptions.server_distribution'))
            ->dataset($serverDistribution['series'])
            ->labels($serverDistribution['labels']);
    }

    /**
     * Create hourly traffic chart
     */
    protected function createHourlyTrafficChart(?int $serverId = null)
    {
        $cacheKey = MonitoringCacheService::CACHE_KEY_HOURLY_TRAFFIC . ($serverId ? '_' . $serverId : '');
        $hourlyTrafficData = $this->cacheService->get($cacheKey, function () use ($serverId) {
            return $this->statsService->getHourlyTraffic($serverId);
        }, $this->cacheService->getDashboardTtl());

        return Chart::make('hourly_traffic', __('monitoring.charts.hourly_traffic'))
            ->type('bar')
            ->height(200)
            ->description(__('monitoring.descriptions.hourly_traffic'))
            ->dataset($hourlyTrafficData['series'])
            ->labels($hourlyTrafficData['labels']);
    }

    /**
     * Create content layout based on server count
     */
    protected function createContentLayout(array $servers, $rightSideLayout)
    {
        if (count($servers) <= 1) {
            $periodTabsLayout = LayoutFactory::tabs($this->createTimePeriodTabs())
                ->slug('time_period_tabs')
                ->lazyload(false);

            return LayoutFactory::split([
                $periodTabsLayout,
                $rightSideLayout
            ])->ratio('60/40');
        } else {
            $serverTabsLayout = LayoutFactory::tabs($this->createServerTabs($servers))
                ->slug('server_tabs')
                ->pills()
                ->lazyload(false);

            return LayoutFactory::split([
                $serverTabsLayout,
                $rightSideLayout
            ])->ratio('60/40');
        }
    }

    /**
     * Create tabs for different time periods (day, week, month)
     */
    protected function createTimePeriodTabs(?int $serverId = null): array
    {
        $periods = ['day', 'week', 'month'];
        $tabs = [];

        foreach ($periods as $period) {
            if ($serverId) {
                $cacheKey = MonitoringCacheService::CACHE_KEY_SERVER_STATS_PREFIX . "{$serverId}_{$period}";
                $data = $this->cacheService->get($cacheKey, function () use ($period, $serverId) {
                    return $this->statsService->calculateServerStatistics($period, $serverId);
                }, $this->cacheService->getDashboardTtl());

                $labels = $data['labels'];

                $tabName = match ($period) {
                    'day' => __('monitoring.tabs.daily_stats'),
                    'week' => __('monitoring.tabs.weekly_stats'),
                    'month' => __('monitoring.tabs.monthly_stats'),
                    default => __('monitoring.tabs.stats')
                };

                $chartName = "server_{$serverId}_{$period}";

                $chart = Chart::make($chartName, __("monitoring.charts.{$period}_stats"))
                    ->type('area')
                    ->height(300)
                    ->description(__("monitoring.descriptions.{$period}_stats"))
                    ->dataset($data['series'])
                    ->labels($labels);
            } else {
                $cacheKey = MonitoringCacheService::CACHE_KEY_ALL_SERVERS_PREFIX . $period;
                $data = $this->cacheService->get($cacheKey, function () use ($period) {
                    return $this->statsService->getMultiServerStatistics($period);
                }, $this->cacheService->getDashboardTtl());

                $labels = $data['labels'];

                $tabName = match ($period) {
                    'day' => __('monitoring.tabs.daily_stats'),
                    'week' => __('monitoring.tabs.weekly_stats'),
                    'month' => __('monitoring.tabs.monthly_stats'),
                    default => __('monitoring.tabs.stats')
                };

                $chartName = "all_servers_{$period}";

                $chart = Chart::make($chartName, __("monitoring.charts.{$period}_stats"))
                    ->type('line')
                    ->height(300)
                    ->description(__("monitoring.descriptions.{$period}_multi_server_stats"))
                    ->dataset($data['series'])
                    ->labels($labels);
            }

            $tabs[] = Tab::make($tabName)
                ->layouts([$chart])
                ->slug($chartName)
                ->active($period === 'day');
        }

        return $tabs;
    }

    /**
     * Create tabs for each server if we have multiple enabled servers
     */
    protected function createServerTabs(array $servers): array
    {
        $tabs = [];

        $allServersTab = Tab::make(__('monitoring.tabs.all_servers'));
        $allServersTab->layouts([
            LayoutFactory::tabs($this->createTimePeriodTabs())
                ->slug('all_servers_period_tabs')
                ->lazyload(false)
        ]);

        $tabs[] = $allServersTab;

        foreach ($servers as $server) {
            $serverTab = Tab::make($server->name)
                ->slug($server->id)
                ->lazyload(false);

            $hourlyTrafficChart = $this->createHourlyTrafficChart($server->id);

            $periodTabs = $this->createTimePeriodTabs($server->id);

            $capacityTab = Tab::make(__('monitoring.charts.capacity_utilization'))
                ->layouts([$hourlyTrafficChart])
                ->slug("server_{$server->id}_capacity");

            $periodTabs[] = $capacityTab;

            $serverTab->layouts([
                LayoutFactory::tabs($periodTabs)
                    ->slug('server_' . $server->id . '_period_tabs')
                    ->lazyload(false)
            ]);

            $tabs[] = $serverTab;
        }

        return $tabs;
    }
}
