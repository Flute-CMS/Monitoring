<?php

namespace Flute\Modules\Monitoring\Services;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;

class MonitoringStatsService
{
    protected array $statuses = [];

    protected array $totalPlayersCount = [];

    protected array $serverStatistics = [];

    protected array $latestServerStatuses = [];

    protected array $yesterdayStats = [];

    protected array $monitoringMetrics = [];

    protected array $gameDistribution = [];

    protected array $serverDistribution = [];

    protected array $multiServerStats = [];

    protected array $hourlyTraffic = [];

    protected MonitoringCacheService $cacheService;

    public function __construct(MonitoringCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function getLatestServerStatusesForEnabledServers(): array
    {
        if (!empty($this->latestServerStatuses)) {
            return $this->latestServerStatuses;
        }

        $servers = $this->cacheService->getEnabledServers();
        if (!$servers) {
            return [];
        }

        $serverIds = array_map(static fn ($s) => $s->id, $servers);
        $yesterday = new DateTimeImmutable('-24 hours');

        $allStatuses = ServerStatus::query()
            ->load('server')
            ->where('server_id', 'IN', new \Cycle\Database\Injection\Parameter($serverIds))
            ->where('updated_at', '>=', $yesterday)
            ->orderBy('updated_at', 'DESC')
            ->fetchAll();

        $statusMap = [];
        foreach ($allStatuses as $status) {
            $serverId = $status->server->id ?? null;
            if ($serverId !== null && !isset($statusMap[$serverId])) {
                $statusMap[$serverId] = $status;
            }
        }

        $this->latestServerStatuses = $statusMap;

        return $statusMap;
    }

    public function getTotalPlayersCount(): array
    {
        if (!empty($this->totalPlayersCount)) {
            return $this->totalPlayersCount;
        }
        $statuses = $this->getLatestServerStatusesForEnabledServers();
        $totalPlayers = 0;
        $totalMaxPlayers = 0;
        foreach ($statuses as $status) {
            if ($status->online) {
                $totalPlayers += $status->players;
                $totalMaxPlayers += $status->max_players;
            }
        }

        $this->totalPlayersCount = [
            'players' => $totalPlayers,
            'max_players' => $totalMaxPlayers,
        ];

        return $this->totalPlayersCount;
    }

    public function getServerStatistics(string $period = 'day', ?int $serverId = null): array
    {
        $cacheKey = $period . '_' . ($serverId ?? 'all');
        if (!empty($this->serverStatistics[$cacheKey])) {
            return $this->serverStatistics[$cacheKey];
        }

        $timezone = new DateTimeZone(config('app.timezone', 'UTC'));
        $now = new DateTimeImmutable('now', $timezone);
        $startDate = $this->getStartDateForPeriod($now, $period);

        $interval = $this->getIntervalForPeriod($period);
        $timeRanges = $this->generateTimeRanges($period, $interval);

        $results = [];

        if ($interval === 'hourly') {
            $results = $this->getHourlyAggregatedData($startDate, $serverId, $timeRanges['keys']);
        } else {
            $results = $this->getDailyAggregatedData($startDate, $serverId, $timeRanges['keys']);
        }

        $this->serverStatistics[$cacheKey] = $results;

        return $results;
    }

    public function calculateServerStatistics(string $period = 'day', ?int $serverId = null): array
    {
        $cacheKey = $period . '_' . ($serverId ?? 'all');
        if (!empty($this->serverStatistics[$cacheKey . '_calculated'])) {
            return $this->serverStatistics[$cacheKey . '_calculated'];
        }

        $statistics = $this->getServerStatistics($period, $serverId);
        $interval = $this->getIntervalForPeriod($period);
        $timeRanges = $this->generateTimeRanges($period, $interval);
        $timeRangeCount = count($timeRanges['keys']);
        $playersData = array_fill(0, $timeRangeCount, 0);
        $serversData = array_fill(0, $timeRangeCount, 0);
        $countData = array_fill(0, $timeRangeCount, 0);
        $maxPlayersData = array_fill(0, $timeRangeCount, 0);
        $maxPlayersInPeriod = array_fill(0, $timeRangeCount, 0);
        $serverTracker = array_fill(0, $timeRangeCount, []);

        $this->processServerStatistics(
            $statistics,
            $timeRanges,
            $serverTracker,
            $playersData,
            $serversData,
            $countData,
            $maxPlayersData,
            $maxPlayersInPeriod,
            $interval,
            $serverId
        );

        if (!$serverId) {
            $this->postProcessServerStatistics($serverTracker, $playersData, $maxPlayersData, $serversData, $countData);
        }

        for ($i = 0; $i < count($playersData); $i++) {
            if ($countData[$i] > 0) {
                $playersData[$i] = round($playersData[$i] / $countData[$i]);
            }
        }

        $result = $this->formatStatisticsResult($serverId, $playersData, $maxPlayersInPeriod, $serversData, $timeRanges['labels']);
        $this->serverStatistics[$cacheKey . '_calculated'] = $result;

        return $result;
    }

    public function calculateMonitoringMetrics(): array
    {
        if (!empty($this->monitoringMetrics)) {
            return $this->monitoringMetrics;
        }

        $servers = $this->cacheService->getEnabledServers();
        $statusMap = $this->getLatestServerStatusesForEnabledServers();
        $totalServers = count($servers);
        $onlineServers = 0;
        $totalPlayers = 0;
        $maxCapacity = 0;

        foreach ($statusMap as $status) {
            if ($status->online) {
                $onlineServers++;
                $totalPlayers += $status->players;
                $maxCapacity += $status->max_players;
            }
        }
        $fillRate = $maxCapacity > 0 ? ($totalPlayers / $maxCapacity) * 100 : 0;
        $yesterdayStats = $this->getYesterdayStats();
        $yesterdayPlayers = $this->calculateYesterdayPlayers($yesterdayStats);
        $playersDiff = $yesterdayPlayers > 0 ? (($totalPlayers - $yesterdayPlayers) / $yesterdayPlayers) * 100 : 0;

        $this->monitoringMetrics = [
            'total_servers' => ['value' => $totalServers, 'diff' => 0],
            'online_servers' => ['value' => $onlineServers, 'diff' => $totalServers > 0 ? ($onlineServers / $totalServers) * 100 : 0],
            'total_players' => ['value' => $totalPlayers, 'diff' => round($playersDiff, 1)],
            'servers_fill' => ['value' => round($fillRate, 1) . '%', 'diff' => 0],
        ];

        return $this->monitoringMetrics;
    }

    public function getGameDistribution(): array
    {
        if (!empty($this->gameDistribution)) {
            return $this->gameDistribution;
        }

        $latestStatuses = $this->getLatestServerStatusesForEnabledServers();

        $gameMap = [];
        foreach ($latestStatuses as $status) {
            if (!$status->online) {
                continue;
            }
            $game = $status->game ?? 'unknown';
            if (!isset($gameMap[$game])) {
                $gameMap[$game] = ['count' => 0, 'players' => 0];
            }
            $gameMap[$game]['count']++;
            $gameMap[$game]['players'] += $status->players;
        }

        uasort($gameMap, static fn ($a, $b) => $b['players'] - $a['players']);
        $gameMap = array_slice($gameMap, 0, 5, true);

        $data = [];
        $labels = [];
        foreach ($gameMap as $game => $stats) {
            $data[] = $stats['players'];
            $labels[] = $game;
        }

        $this->gameDistribution = ['series' => $data, 'labels' => $labels];

        return $this->gameDistribution;
    }

    public function getServerDistribution(): array
    {
        if (!empty($this->serverDistribution)) {
            return $this->serverDistribution;
        }

        $latestStatuses = $this->getLatestServerStatusesForEnabledServers();

        $serverMap = [];
        foreach ($latestStatuses as $status) {
            if (!$status->online) {
                continue;
            }
            $serverMap[$status->server->id] = [
                'name' => $status->server->name ?? __('monitoring.unknown_server'),
                'players' => $status->players,
                'max_players' => $status->max_players,
            ];
        }

        uasort($serverMap, static fn ($a, $b) => $b['players'] - $a['players']);
        $serverMap = array_slice($serverMap, 0, 5, true);

        $data = [];
        $labels = [];
        foreach ($serverMap as $stats) {
            $data[] = $stats['players'];
            $labels[] = $stats['name'];
        }

        $this->serverDistribution = ['series' => $data, 'labels' => $labels];

        return $this->serverDistribution;
    }

    public function getMultiServerStatistics(string $period = 'day'): array
    {
        $selectedServerId = request()->input('tab-server_tabs');
        $cacheKey = $period . '_' . ($selectedServerId ?? 'all');

        if (!empty($this->multiServerStats[$cacheKey])) {
            return $this->multiServerStats[$cacheKey];
        }

        $servers = $this->cacheService->getEnabledServers();
        $result = ['labels' => [], 'series' => []];
        $interval = $this->getIntervalForPeriod($period);
        $timeRanges = $this->generateTimeRanges($period, $interval);
        $result['labels'] = $timeRanges['labels'];

        foreach ($servers as $server) {
            if ($selectedServerId && $selectedServerId != $server->id) {
                continue;
            }

            $serverStats = $this->calculateServerStatistics($period, $server->id);
            $this->addServerDataToMultiSeries($result['series'], $serverStats, $server);
        }

        $this->multiServerStats[$cacheKey] = $result;

        return $result;
    }

    public function getHourlyTraffic(?int $serverId = null): array
    {
        $cacheKey = $serverId ?? 'all';

        if (!empty($this->hourlyTraffic[$cacheKey])) {
            return $this->hourlyTraffic[$cacheKey];
        }

        $timezone = new DateTimeZone(config('app.timezone', 'UTC'));
        $startDate = new DateTimeImmutable('-7 days', $timezone);
        $statistics = $this->getOptimizedHourlyTrafficStatistics($startDate, $serverId);
        $hourlyData = $this->aggregateHourlyTrafficData($statistics);
        $result = $this->formatHourlyTrafficData($hourlyData);

        $this->hourlyTraffic[$cacheKey] = $result;

        return $result;
    }

    protected function getHourlyAggregatedData(DateTimeImmutable $startDate, ?int $serverId, array $timeKeys): array
    {
        if (empty($timeKeys)) {
            return [];
        }

        $servers = $serverId
            ? [Server::findByPK($serverId)]
            : $this->cacheService->getEnabledServers();

        $servers = array_filter($servers);
        if (empty($servers)) {
            return [];
        }

        $serverIds = array_map(static fn ($s) => $s->id, $servers);

        $allStatuses = ServerStatus::query()
            ->load('server')
            ->where('server_id', 'IN', new \Cycle\Database\Injection\Parameter($serverIds))
            ->where('updated_at', '>=', $startDate)
            ->orderBy('updated_at', 'DESC')
            ->fetchAll();

        return $allStatuses;
    }

    protected function getDailyAggregatedData(DateTimeImmutable $startDate, ?int $serverId, array $timeKeys): array
    {
        if (empty($timeKeys)) {
            return [];
        }

        $servers = $serverId
            ? [Server::findByPK($serverId)]
            : $this->cacheService->getEnabledServers();

        $servers = array_filter($servers);
        if (empty($servers)) {
            return [];
        }

        $serverIds = array_map(static fn ($s) => $s->id, $servers);

        $allStatuses = ServerStatus::query()
            ->load('server')
            ->where('server_id', 'IN', new \Cycle\Database\Injection\Parameter($serverIds))
            ->where('updated_at', '>=', $startDate)
            ->orderBy('updated_at', 'DESC')
            ->fetchAll();

        return $allStatuses;
    }

    protected function getStartDateForPeriod(DateTimeImmutable $now, string $period): DateTimeImmutable
    {
        switch ($period) {
            case 'week':
                return $now->modify('-7 days');
            case 'month':
                return $now->modify('-30 days');
            case 'day':
            default:
                return $now->modify('-24 hours');
        }
    }

    protected function processServerStatistics(
        array $statistics,
        array $timeRanges,
        array &$serverTracker,
        array &$playersData,
        array &$serversData,
        array &$countData,
        array &$maxPlayersData,
        array &$maxPlayersInPeriod,
        string $interval,
        ?int $targetServerId
    ): void {
        $timezone = config('app.timezone', 'UTC');

        $timeKeyMap = array_flip($timeRanges['keys']);

        foreach ($statistics as $stat) {
            $date = Carbon::parse($stat->updated_at)->timezone($timezone);
            $key = $this->getTimeKey($date, $interval);

            if (!isset($timeKeyMap[$key])) {
                continue;
            }

            $keyIndex = $timeKeyMap[$key];
            $server_id = $stat->server->id;
            $players = $stat->players;
            $max_players = $stat->max_players;
            $online = $stat->online;
            $updated_at = $stat->updated_at;

            if (!isset($serverTracker[$keyIndex][$server_id])) {
                $serverTracker[$keyIndex][$server_id] = [
                    'players' => 0,
                    'max_players' => 0,
                    'online' => false,
                    'updated_at' => null,
                ];
            }

            $existingTimestamp = $serverTracker[$keyIndex][$server_id]['updated_at'];

            if ($existingTimestamp === null || $updated_at > $existingTimestamp) {
                $serverTracker[$keyIndex][$server_id] = [
                    'players' => $players,
                    'max_players' => $max_players,
                    'online' => $online,
                    'updated_at' => $updated_at,
                ];
            }

            if ($targetServerId && $server_id == $targetServerId) {
                $playersData[$keyIndex] += $players;
                $maxPlayersInPeriod[$keyIndex] = max($maxPlayersInPeriod[$keyIndex], $players);
                if ($online) {
                    $serversData[$keyIndex]++;
                }
                $countData[$keyIndex]++;
            }
        }
    }

    protected function getTimeKey(Carbon $date, string $interval): string
    {
        return $interval === 'hourly' ? $date->format('Y-m-d H') : $date->format('Y-m-d');
    }

    protected function postProcessServerStatistics(
        array $serverTracker,
        array &$playersData,
        array &$maxPlayersData,
        array &$serversData,
        array &$countData
    ): void {
        for ($i = 0; $i < count($serverTracker); $i++) {
            if (!empty($serverTracker[$i])) {
                $slotOnlineServers = 0;
                $slotTotalPlayers = 0;
                $slotMaxPlayers = 0;
                foreach ($serverTracker[$i] as $serverData) {
                    $slotTotalPlayers += $serverData['players'];
                    $slotMaxPlayers += $serverData['max_players'];
                    if ($serverData['online']) {
                        $slotOnlineServers++;
                    }
                }
                $playersData[$i] = $slotTotalPlayers;
                $maxPlayersData[$i] = $slotMaxPlayers;
                $serversData[$i] = $slotOnlineServers;
                $countData[$i] = count($serverTracker[$i]);
            }
        }
    }

    protected function formatStatisticsResult(
        ?int $serverId,
        array $playersData,
        array $maxPlayersInPeriod,
        array $serversData,
        array $labels
    ): array {
        if ($serverId) {
            return [
                'series' => [
                    ['name' => __('monitoring.charts.players'), 'data' => $playersData],
                    ['name' => __('monitoring.charts.max_players_period'), 'data' => $maxPlayersInPeriod],
                ],
                'labels' => $labels,
            ];
        }

        return [
            'series' => [
                ['name' => __('monitoring.charts.players'), 'data' => $playersData],
                ['name' => __('monitoring.charts.servers'), 'data' => $serversData],
            ],
            'labels' => $labels,
        ];

    }

    protected function getIntervalForPeriod(string $period): string
    {
        switch ($period) {
            case 'day':
                return 'hourly';
            case 'week':
            case 'month':
                return 'daily';
            default:
                return 'hourly';
        }
    }

    protected function generateTimeRanges(string $period, string $interval): array
    {
        $keys = [];
        $labels = [];
        $timezone = new DateTimeZone(config('app.timezone', 'UTC'));
        $now = Carbon::now($timezone);
        switch ($interval) {
            case 'hourly':
                $this->generateHourlyTimeRanges($now, $keys, $labels);

                break;
            case 'daily':
                $days = $period === 'week' ? 7 : 30;
                $this->generateDailyTimeRanges($now, $days, $keys, $labels);

                break;
        }

        return ['keys' => $keys, 'labels' => $labels];
    }

    protected function generateHourlyTimeRanges(Carbon $now, array &$keys, array &$labels): void
    {
        for ($i = 0; $i < 24; $i++) {
            $hour = $now->copy()->subHours(23 - $i);
            if ($hour->gt($now)) {
                continue;
            }
            $labels[] = $hour->format('H:00');
            $keys[] = $hour->format('Y-m-d H');
        }
    }

    protected function generateDailyTimeRanges(Carbon $now, int $days, array &$keys, array &$labels): void
    {
        for ($i = 0; $i < $days; $i++) {
            $day = $now->copy()->subDays($days - 1 - $i);
            if ($day->gt($now)) {
                continue;
            }
            $labels[] = $day->format('d M');
            $keys[] = $day->format('Y-m-d');
        }
    }

    protected function getOptimizedHourlyTrafficStatistics(DateTimeImmutable $startDate, ?int $serverId): array
    {
        $servers = [];
        if ($serverId) {
            $server = Server::findByPK($serverId);
            if ($server && $server->enabled) {
                $servers = [$server];
            }
        } else {
            $servers = $this->cacheService->getEnabledServers();
        }

        if (empty($servers)) {
            return [];
        }

        $serverIds = array_map(static fn ($s) => $s->id, $servers);

        $query = ServerStatus::query()
            ->load('server')
            ->where('server_id', 'IN', new \Cycle\Database\Injection\Parameter($serverIds))
            ->where('updated_at', '>=', $startDate)
            ->where('online', true)
            ->orderBy('updated_at', 'DESC');

        return $query->fetchAll();
    }

    protected function getYesterdayStats(): array
    {
        if (!empty($this->yesterdayStats)) {
            return $this->yesterdayStats;
        }

        $timezone = new DateTimeZone(config('app.timezone', 'UTC'));
        $yesterday = new DateTimeImmutable('-24 hours', $timezone);
        $dayBefore = new DateTimeImmutable('-48 hours', $timezone);

        $servers = $this->cacheService->getEnabledServers();
        if (empty($servers)) {
            return [];
        }

        $serverIds = array_map(static fn ($s) => $s->id, $servers);

        $allStatuses = ServerStatus::query()
            ->load('server')
            ->where('server_id', 'IN', new \Cycle\Database\Injection\Parameter($serverIds))
            ->where('updated_at', '>=', $dayBefore)
            ->where('updated_at', '<', $yesterday)
            ->orderBy('updated_at', 'DESC')
            ->fetchAll();

        $serverLatest = [];
        $seenServers = [];

        foreach ($allStatuses as $status) {
            $serverId = $status->server->id ?? null;
            if ($serverId !== null && !isset($seenServers[$serverId])) {
                $seenServers[$serverId] = true;
                $serverLatest[] = $status;
            }
        }

        $this->yesterdayStats = $serverLatest;

        return $serverLatest;
    }

    protected function calculateYesterdayPlayers(array $yesterdayStats): int
    {
        $yesterdayPlayers = 0;
        foreach ($yesterdayStats as $stat) {
            if ($stat->online) {
                $yesterdayPlayers += $stat->players;
            }
        }

        return $yesterdayPlayers;
    }

    protected function addServerDataToMultiSeries(array &$series, array $serverStats, Server $server): void
    {
        foreach ($serverStats['series'] as $serieData) {
            if ($serieData['name'] === __('monitoring.charts.players')) {
                $series[] = ['name' => $server->name, 'data' => $serieData['data']];

                break;
            }
        }
    }

    protected function aggregateHourlyTrafficData(array $statistics): array
    {
        $hourlyData = array_fill(0, 24, ['players' => 0, 'count' => 0, 'max_players' => 0]);
        $timezone = config('app.timezone', 'UTC');
        foreach ($statistics as $stat) {
            if ($stat->online) {
                $date = Carbon::parse($stat->updated_at)->timezone($timezone);
                $hour = (int)$date->format('G');
                $hourlyData[$hour]['players'] += $stat->players;
                $hourlyData[$hour]['max_players'] += $stat->max_players;
                $hourlyData[$hour]['count']++;
            }
        }

        return $hourlyData;
    }

    protected function formatHourlyTrafficData(array $hourlyData): array
    {
        $playersData = [];
        $maxPlayersData = [];
        $labels = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = sprintf("%02d:00", $hour);
            if ($hourlyData[$hour]['count'] > 0) {
                $playersData[] = round($hourlyData[$hour]['players'] / $hourlyData[$hour]['count']);
                $maxPlayersData[] = round($hourlyData[$hour]['max_players'] / $hourlyData[$hour]['count']);
            } else {
                $playersData[] = 0;
                $maxPlayersData[] = 0;
            }
        }

        return [
            'series' => [
                ['name' => __('monitoring.charts.players'), 'data' => $playersData],
                ['name' => __('monitoring.charts.max_players'), 'data' => $maxPlayersData],
            ],
            'labels' => $labels,
        ];
    }
}
