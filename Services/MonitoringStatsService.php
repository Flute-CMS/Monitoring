<?php

namespace Flute\Modules\Monitoring\Services;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Carbon\Carbon;
use Cycle\Database\Injection\Expression;

class MonitoringStatsService
{
    public function getLatestServerStatusesForEnabledServers(): array
    {
        $servers = Server::query()->where('enabled', true)->fetchAll();

        $statusMap = [];
        foreach ($servers as $server) {
            $statuses = ServerStatus::query()
                ->where('server.id', $server->id)
                ->orderBy('updated_at', 'DESC')
                ->limit(1)
                ->fetchAll();

            $statusMap[$server->id] = $statuses[0];
        }
        return $statusMap;
    }

    public function getTotalPlayersCount(): array
    {
        $statuses = $this->getLatestServerStatusesForEnabledServers();
        $totalPlayers = 0;
        $totalMaxPlayers = 0;
        foreach ($statuses as $status) {
            if ($status->online) {
                $totalPlayers += $status->players;
                $totalMaxPlayers += $status->max_players;
            }
        }
        return [
            'players' => $totalPlayers,
            'max_players' => $totalMaxPlayers
        ];
    }

    public function getServerStatistics(string $period = 'day', ?int $serverId = null): array
    {
        $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));
        $now = new \DateTimeImmutable('now', $timezone);
        $startDate = $this->getStartDateForPeriod($now, $period);
        $query = ServerStatus::query()->where('updated_at', '>=', $startDate);

        if ($serverId) {
            $query = $query->where('server.id', $serverId);
        }

        $query->orderBy('updated_at', 'DESC');

        $serverIds = [];
        if ($serverId) {
            $serverIds = [$serverId];
        } else {
            $serverIdQuery = clone $query;
            $serverIdResults = $serverIdQuery->select('server.id')->groupBy('server.id')->fetchAll();
            foreach ($serverIdResults as $result) {
                $serverIds[] = $result->server->id;
            }
        }

        $interval = $this->getIntervalForPeriod($period);
        $timeRanges = $this->generateTimeRanges($period, $interval);

        $results = [];

        foreach ($serverIds as $currentServerId) {
            $singleServerQuery = ServerStatus::query()
                ->where('updated_at', '>=', $startDate)
                ->where('server.id', $currentServerId);

            foreach ($timeRanges['keys'] as $key) {
                if ($interval === 'hourly') {
                    $keyParts = explode(' ', $key);
                    if (count($keyParts) === 2) {
                        $datePart = $keyParts[0];
                        $hourPart = (int)$keyParts[1];

                        $startHour = new \DateTimeImmutable("{$datePart} {$hourPart}:00:00", $timezone);
                        $endHour = new \DateTimeImmutable("{$datePart} {$hourPart}:59:59", $timezone);

                        $hourlyResult = clone $singleServerQuery;
                        $hourlyResult->where('updated_at', '>=', $startHour)
                            ->where('updated_at', '<=', $endHour)
                            ->orderBy('updated_at', 'DESC')
                            ->limit(1);

                        $record = $hourlyResult->fetchOne();
                        if ($record) {
                            $results[] = $record;
                        }
                    }
                } else if ($interval === 'daily') {
                    $startDay = new \DateTimeImmutable("{$key} 00:00:00", $timezone);
                    $endDay = new \DateTimeImmutable("{$key} 23:59:59", $timezone);

                    $dailyResult = clone $singleServerQuery;
                    $dailyResult->where('updated_at', '>=', $startDay)
                        ->where('updated_at', '<=', $endDay)
                        ->orderBy('updated_at', 'DESC')
                        ->limit(1);

                    $record = $dailyResult->fetchOne();
                    if ($record) {
                        $results[] = $record;
                    }
                }
            }
        }

        return $results;
    }

    protected function getStartDateForPeriod(\DateTimeImmutable $now, string $period): \DateTimeImmutable
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

    public function calculateServerStatistics(string $period = 'day', ?int $serverId = null): array
    {
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

        return $this->formatStatisticsResult($serverId, $playersData, $maxPlayersInPeriod, $serversData, $timeRanges['labels']);
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
        foreach ($statistics as $stat) {
            $date = Carbon::parse($stat->updated_at)->timezone($timezone);
            $key = $this->getTimeKey($date, $interval);
            $keyIndex = array_search($key, $timeRanges['keys']);

            if ($keyIndex !== false) {
                $currentStatServerId = $stat->server->id;
                if (!isset($serverTracker[$keyIndex][$currentStatServerId])) {
                    $serverTracker[$keyIndex][$currentStatServerId] = [
                        'players' => 0,
                        'max_players' => 0,
                        'online' => false,
                        'updated_at' => null
                    ];
                }

                $existingTimestamp = $serverTracker[$keyIndex][$currentStatServerId]['updated_at'];
                if ($existingTimestamp === null || $date->gt(Carbon::parse($existingTimestamp)->timezone($timezone))) {
                    $serverTracker[$keyIndex][$currentStatServerId] = [
                        'players' => $stat->players,
                        'max_players' => $stat->max_players,
                        'online' => $stat->online,
                        'updated_at' => $stat->updated_at
                    ];
                }

                if ($targetServerId && $currentStatServerId == $targetServerId) {
                    $playersData[$keyIndex] += $stat->players;
                    if ($stat->players > $maxPlayersInPeriod[$keyIndex]) {
                        $maxPlayersInPeriod[$keyIndex] = $stat->players;
                    }
                    if ($stat->online) {
                        $serversData[$keyIndex]++;
                    }
                    $countData[$keyIndex]++;
                }
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
                    ['name' => __('monitoring.charts.max_players_period'), 'data' => $maxPlayersInPeriod]
                ],
                'labels' => $labels
            ];
        } else {
            return [
                'series' => [
                    ['name' => __('monitoring.charts.players'), 'data' => $playersData],
                    ['name' => __('monitoring.charts.servers'), 'data' => $serversData]
                ],
                'labels' => $labels
            ];
        }
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
        $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));
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
            if ($hour->gt($now)) continue;
            $labels[] = $hour->format('H:00');
            $keys[] = $hour->format('Y-m-d H');
        }
    }

    protected function generateDailyTimeRanges(Carbon $now, int $days, array &$keys, array &$labels): void
    {
        for ($i = 0; $i < $days; $i++) {
            $day = $now->copy()->subDays($days - 1 - $i);
            if ($day->gt($now)) continue;
            $labels[] = $day->format('d M');
            $keys[] = $day->format('Y-m-d');
        }
    }

    public function calculateMonitoringMetrics(): array
    {
        $servers = Server::findAll(['enabled' => true]); // This might be better if MonitoringService provides it
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

        return [
            'total_servers' => ['value' => $totalServers, 'diff' => 0],
            'online_servers' => ['value' => $onlineServers, 'diff' => $totalServers > 0 ? ($onlineServers / $totalServers) * 100 : 0],
            'total_players' => ['value' => $totalPlayers, 'diff' => round($playersDiff, 1)],
            'servers_fill' => ['value' => round($fillRate, 1) . '%', 'diff' => 0],
        ];
    }

    protected function getYesterdayStats(): array
    {
        $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));
        $yesterday = new \DateTimeImmutable('-24 hours', $timezone);
        $dayBefore = new \DateTimeImmutable('-48 hours', $timezone);
        return ServerStatus::query()
            ->where('updated_at', '>=', $dayBefore)
            ->where('updated_at', '<', $yesterday)
            ->orderBy('updated_at', 'DESC')
            ->groupBy('server.id')
            ->fetchAll();
    }

    protected function calculateYesterdayPlayers(array $yesterdayStats): int
    {
        $yesterdayStatusMap = [];
        foreach ($yesterdayStats as $stat) {
            $serverId = $stat->server->id;
            if (!isset($yesterdayStatusMap[$serverId]) || $stat->updated_at > $yesterdayStatusMap[$serverId]->updated_at) {
                $yesterdayStatusMap[$serverId] = $stat;
            }
        }
        $yesterdayPlayers = 0;
        foreach ($yesterdayStatusMap as $stat) {
            if ($stat->online) $yesterdayPlayers += $stat->players;
        }
        return $yesterdayPlayers;
    }

    public function getGameDistribution(): array
    {
        $statuses = ServerStatus::query()
            ->where('server.enabled', true)
            ->where('online', true)
            ->orderBy('updated_at', 'DESC')
            ->groupBy('server.id')
            ->fetchAll();

        $gameMap = $this->aggregateGameDistribution($statuses);
        return $this->formatDistributionDataForGames($gameMap); // Renamed for clarity
    }

    protected function aggregateGameDistribution(array $statuses): array
    {
        $gameMap = [];
        foreach ($statuses as $status) {
            if (!$status->online) continue;
            $game = $status->game ?? 'unknown';
            if (!isset($gameMap[$game])) {
                $gameMap[$game] = ['count' => 0, 'players' => 0];
            }
            $gameMap[$game]['count']++;
            $gameMap[$game]['players'] += $status->players;
        }
        uasort($gameMap, fn($a, $b) => $b['players'] - $a['players']);
        return $gameMap;
    }

    protected function formatDistributionDataForGames(array $distributionMap): array // Renamed
    {
        $data = [];
        $labels = [];
        $counter = 0;
        foreach ($distributionMap as $key => $stats) {
            $data[] = $stats['players'];
            $labels[] = $key;
            $counter++;
            if ($counter >= 5) break;
        }
        return ['series' => $data, 'labels' => $labels];
    }

    public function getServerDistribution(): array
    {
        $statuses = ServerStatus::query()
            ->where('server.enabled', true)
            ->where('online', true)
            ->orderBy('updated_at', 'DESC')
            ->groupBy('server.id')
            ->fetchAll();

        $serverMap = $this->aggregateServerDistribution($statuses);
        return $this->formatDistributionDataForServers($serverMap);
    }

    protected function aggregateServerDistribution(array $statuses): array
    {
        $serverMap = [];
        foreach ($statuses as $status) {
            if (!$status->online) continue;
            $serverName = $status->server->name ?? __('monitoring.unknown_server');
            $serverMap[$status->server->id] = [
                'name' => $serverName,
                'players' => $status->players,
                'max_players' => $status->max_players
            ];
        }
        uasort($serverMap, fn($a, $b) => $b['players'] - $a['players']);
        return $serverMap;
    }

    protected function formatDistributionDataForServers(array $distributionMap): array
    {
        $data = [];
        $labels = [];
        $counter = 0;
        foreach ($distributionMap as $serverId => $stats) {
            $data[] = $stats['players'];
            $labels[] = $stats['name']; // Use server name for labels
            $counter++;
            if ($counter >= 5) break;
        }
        return ['series' => $data, 'labels' => $labels];
    }

    public function getMultiServerStatistics(string $period = 'day'): array
    {
        $servers = Server::findAll(['enabled' => true]);
        $result = ['labels' => [], 'series' => []];
        $interval = $this->getIntervalForPeriod($period);
        $timeRanges = $this->generateTimeRanges($period, $interval);
        $result['labels'] = $timeRanges['labels'];

        $selectedServerId = request()->input('tab-server_tabs');

        foreach ($servers as $server) {
            if ($selectedServerId && $selectedServerId != $server->id) {
                continue;
            }

            $serverStats = $this->calculateServerStatistics($period, $server->id);
            $this->addServerDataToMultiSeries($result['series'], $serverStats, $server);
        }
        return $result;
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

    public function getHourlyTraffic(?int $serverId = null): array
    {
        $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));
        $startDate = new \DateTimeImmutable('-7 days', $timezone);
        $statistics = $this->getOptimizedHourlyTrafficStatistics($startDate, $serverId);
        $hourlyData = $this->aggregateHourlyTrafficData($statistics);
        return $this->formatHourlyTrafficData($hourlyData);
    }

    protected function getOptimizedHourlyTrafficStatistics(\DateTimeImmutable $startDate, ?int $serverId): array
    {
        $query = ServerStatus::query()->where('updated_at', '>=', $startDate);
        if ($serverId) {
            $query = $query->where('server.id', $serverId);
        }

        $timezone = config('app.timezone', 'UTC');
        $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        $results = [];

        $serverIds = [];
        if ($serverId) {
            $serverIds = [$serverId];
        } else {
            $serverIdQuery = clone $query;
            $serverIdResults = $serverIdQuery->columns('server.id')->groupBy('server.id')->fetchAll();
            foreach ($serverIdResults as $result) {
                $serverIds[] = $result->server->id;
            }
        }

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[] = $h;
        }

        foreach ($serverIds as $sid) {
            $serverQuery = ServerStatus::query()
                ->where('updated_at', '>=', $startDate)
                ->where('server.id', $sid)
                ->where('online', true);

            foreach ($hours as $hour) {
                $hourSamples = clone $serverQuery;
                $hourSamples->where(function ($q) use ($hour) {
                    $q->where(new Expression('HOUR(serverStatus.updated_at) = ?', [$hour]));
                })->orderBy('updated_at', 'DESC')->limit(1);

                $record = $hourSamples->fetchOne();
                if ($record) {
                    $results[] = $record;
                }
            }
        }

        return $results;
    }

    protected function getHourlyTrafficStatistics(\DateTimeImmutable $startDate, ?int $serverId): array
    {
        $query = ServerStatus::query()->where('updated_at', '>=', $startDate);
        if ($serverId) {
            $query = $query->where('server.id', $serverId);
        }
        return $query->fetchAll();
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
                ['name' => __('monitoring.charts.max_players'), 'data' => $maxPlayersData]
            ],
            'labels' => $labels
        ];
    }
}
