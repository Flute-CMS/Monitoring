<?php

namespace Flute\Modules\Monitoring\Services;

use Cycle\Database\Exception\StatementException\ConstrainException;
use Cycle\Database\Injection\Parameter;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Rcon\RconService;
use Flute\Core\ServerQuery\QueryResult;
use Flute\Core\ServerQuery\ServerQueryService;
use Flute\Core\Services\DatabaseService;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Throwable;

class MonitoringService
{
    public const STATUS_RETENTION_DAYS = 60;

    public const CONNECTION_TIMEOUT = 3;

    public const GAME_CSGO = '730';

    public const GAME_CSS = '240';

    public const GAME_CS16 = '10';

    public const GAME_TF2 = '440';

    public const GAME_L4D2 = '550';

    public const GAME_GARRYSMOD = '4000';

    public const GAME_MINECRAFT = 'minecraft';

    public const GAME_GTA5 = 'gta5';

    public const GAME_SAMP = 'samp';

    public const GAME_RUST = 'rust';

    public const BATCH_SIZE = 10;

    /**
     * Global monitoring setting: measure and show latency / ping UI (admin → Monitoring).
     */
    public static function isPingEnabled(): bool
    {
        return filter_var(config('monitoring.show_ping', true), FILTER_VALIDATE_BOOLEAN);
    }

    private ServerQueryService $queryService;

    private RconService $rconService;

    private MonitoringCacheService $cacheService;

    private MapImageService $mapImageService;

    private MonitoringStatsService $statsService;

    private MonitoringPingService $pingService;

    private GeoIpService $geoIpService;

    private ?array $servers = null;

    private string $serversSignature = '';

    public function __construct(
        ServerQueryService $queryService,
        RconService $rconService,
        MonitoringCacheService $cacheService,
        MapImageService $mapImageService,
        MonitoringStatsService $statsService,
        MonitoringPingService $pingService,
        GeoIpService $geoIpService,
    ) {
        $this->queryService = $queryService;
        $this->rconService = $rconService;
        $this->cacheService = $cacheService;
        $this->mapImageService = $mapImageService;
        $this->statsService = $statsService;
        $this->geoIpService = $geoIpService;
        $this->pingService = $pingService;
    }

    /**
     * Get user coordinates by IP from request.
     *
     * @return array{lat: float, lon: float}|null
     */
    public function getUserCoordinates(): ?array
    {
        return $this->geoIpService->getCoordinates(request()->getClientIp());
    }

    /**
     * Auto-fill server coordinates from IP if not set.
     * Called during cron status update cycle.
     *
     * @param Server[] $servers
     */
    private function autoFillServerCoordinates(array $servers): void
    {
        foreach ($servers as $server) {
            if (!$server->enabled) {
                continue;
            }

            if ($server->getSetting('lat') && $server->getSetting('lon')) {
                continue;
            }

            $coords = $this->geoIpService->getCoordinates($server->ip);

            if ($coords) {
                $settings = $server->getSettings();
                $settings['lat'] = $coords['lat'];
                $settings['lon'] = $coords['lon'];
                $server->setSettings($settings);
                $server->save();
            }
        }
    }

    public function getAllServers(bool $forceRefresh = false): array
    {
        $serversList = $this->cacheService->getEnabledServers();
        $signature = $this->generateServersSignature($serversList);

        if ($forceRefresh || $this->servers === null || $this->serversSignature !== $signature) {
            $this->serversSignature = $signature;
            $this->servers = $this->fetchEnabledServersWithStatuses($serversList);
        }

        return $this->servers;
    }

    public function getServerById(int $id): ?array
    {
        if (!$id) {
            return null;
        }

        $allServers = $this->getAllServers();

        foreach ($allServers as $serverData) {
            if ($serverData['server']->id === $id) {
                return $serverData;
            }
        }

        return null;
    }

    public function getActiveServers(): array
    {
        return array_filter(
            $this->getAllServers(),
            static fn($serverData) => $serverData['server']->enabled && $serverData['status']->online === true,
        );
    }

    public function getInactiveServers(): array
    {
        return array_filter(
            $this->getAllServers(),
            static fn($serverData) => $serverData['server']->enabled && $serverData['status']->online === false,
        );
    }

    public function updateServerStatus(Server $server): ?ServerStatus
    {
        $status = new ServerStatus();
        $status->server = $server;
        $status->online = false;

        try {
            $queryResult = $this->queryService->query($server, self::CONNECTION_TIMEOUT);
            $this->applyQueryResult($queryResult, $status, $server);
        } catch (Exception $e) {
            $serverId = $this->getServerIdentifier($server);
            logs()->error("Error updating server status for {$serverId}: {$e->getMessage()}");
        }

        if ($status->online && $status->game === self::GAME_CSGO) {
            $this->fetchAndAddSteamInfo($status);
        }

        $this->saveStatus($status);
        $this->clearServersCache();

        return $status;
    }

    /**
     * Test server connection without saving to database
     */
    public function testServerConnection(string $ip, int $port, string $mod): ?ServerStatus
    {
        $status = new ServerStatus();
        $status->online = false;

        try {
            $queryResult = $this->queryService->queryRaw($ip, $port, $mod, self::CONNECTION_TIMEOUT);

            if ($queryResult->online) {
                $status->online = true;
                $status->players = $queryResult->players;
                $status->max_players = $queryResult->maxPlayers;
                $status->map = $queryResult->map;
                $status->game = $queryResult->game;
                $status->setPlayersData($this->formatPlayersData($queryResult));
                $status->touch();
            }
        } catch (Exception $e) {
            $status->online = false;
        }

        return $status;
    }

    public function updateAllServersStatus(): void
    {
        $servers = $this->cacheService->getEnabledServers();

        if (self::isPingEnabled()) {
            // Measure pings BEFORE heavy query cycle to get clean network latency
            $this->pingService->updatePings($servers);
        } else {
            cache()->delete('monitoring_pings');
        }

        $this->autoFillServerCoordinates($servers);

        $batches = array_chunk($servers, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            $this->processBatch($batch);

            // Clear entity manager to free memory after each batch
            if (method_exists(db(), 'getEntityManager')) {
                try {
                    db()->getEntityManager()->clear();
                } catch (Throwable $e) {
                    // @mago-expect no-empty-catch-clause
                }
            }
        }

        $this->pruneOldStatuses();
        $this->clearServersCache();
    }

    public function setupCron(): void
    {
        if (config('app.cron_mode')) {
            $this->setupScheduledMonitoring();
        } else {
            $this->setupCacheBasedMonitoring();
        }
    }

    public function getMapPreview(?ServerStatus $status): string
    {
        return $this->mapImageService->getMapPreview($status);
    }

    public function getTotalPlayersCount(): array
    {
        $cacheKey = MonitoringCacheService::CACHE_KEY_TOTAL_PLAYERS;

        return $this->cacheService->get($cacheKey, fn() => $this->statsService->getTotalPlayersCount(), 60); // 1 minute
    }

    public function getServerStats(Server $server): ?array
    {
        $dbMod = app(DatabaseService::class)->getConnectionInfoByServerId(
            $server->id,
            [
                'LR',
            ],
        );

        return $dbMod;
    }

    public function getPlayerRank(int $steamId, ?array $dbMod): ?string
    {
        if (empty($dbMod)) {
            return null;
        }

        $driver = $dbMod['connection'];
        $server = $dbMod['server'];
        $prefix = '';
        $tableName = $dbMod['connection']->getAdditional()['table_name'] ?? 'base';

        if (!config('database.databases.' . $driver->dbname . '.prefix')) {
            $prefix = 'lvl_';
        }

        $playerRank = db($driver->dbname)
            ->select()
            ->from($prefix . $tableName)
            ->where('steam', 'like', '%' . $this->convertSteamIdToDatabaseId($steamId))
            ->fetchAll();

        if (empty($playerRank)) {
            return '<img src="' . asset('assets/img/ranks/default/0.webp') . '" alt="0" loading="lazy">';
        }

        $playerRank = $playerRank[0];

        if (method_exists($server, 'getRank')) {
            return $server->getRank($playerRank['rank'] ?? 0, $playerRank['value'] ?? 0);
        }

        return (
            '<img src="'
            . asset(
                'assets/img/ranks/'
                . ( $server->ranks ?? 'default' )
                . '/'
                . ( !empty($playerRank['rank']) ? $playerRank['rank'] : '0' )
                . '.'
                . ( $server->ranks_format ?? 'webp' ),
            )
            . '" alt="'
            . $playerRank['rank']
            . '" loading="lazy">'
        );
    }

    /**
     * Process a batch of servers using parallel queries where possible.
     */
    protected function processBatch(array $servers): void
    {
        // Batch query all servers in parallel
        $queryResults = $this->queryService->queryBatch($servers, self::CONNECTION_TIMEOUT);

        foreach ($servers as $server) {
            try {
                $status = new ServerStatus();
                $status->server = $server;
                $status->online = false;

                $queryResult = $queryResults[$server->id] ?? new QueryResult();
                $this->applyQueryResult($queryResult, $status, $server);

                if ($status->online && $status->game === self::GAME_CSGO) {
                    $this->fetchAndAddSteamInfo($status);
                }

                $this->saveStatus($status);
            } catch (Exception $e) {
                $serverId = $this->getServerIdentifier($server);
                logs()->error("Error updating server status for {$serverId}: {$e->getMessage()}");

            }
        }
    }

    /**
     * Apply query result to server status, with mm_getinfo RCON enrichment for CS2/CS:GO.
     */
    protected function applyQueryResult(QueryResult $queryResult, ServerStatus $status, Server $server): void
    {
        if (!$queryResult->online) {
            $status->online = false;
            $status->touch();

            return;
        }

        // CS2/CS:GO with RCON: try mm_getinfo for accurate player data with Steam IDs
        if ($server->mod === self::GAME_CSGO && !empty($server->rcon)) {
            if ($this->tryMMInfoUpdate($queryResult, $status, $server)) {
                return;
            }
        }

        $status->online = true;
        $status->players = $queryResult->players;
        $status->max_players = $queryResult->maxPlayers;
        $status->map = $queryResult->map;
        $status->game = $queryResult->game;
        $status->setPlayersData($this->formatPlayersData($queryResult));

        // Store game description for CS2/CS:GO distinction and other metadata
        if (!empty($queryResult->additional)) {
            $meta = [];

            if (isset($queryResult->additional['description'])) {
                $meta['description'] = $queryResult->additional['description'];
            }

            if (isset($queryResult->additional['app_id'])) {
                $meta['app_id'] = $queryResult->additional['app_id'];
            }

            if (isset($queryResult->additional['vac'])) {
                $meta['vac'] = $queryResult->additional['vac'];
            }

            if (!empty($meta)) {
                $status->setAdditional($meta);
            }
        }

        $status->touch();
    }

    /**
     * Try to get CS2/CS:GO player data via RCON mm_getinfo command.
     * Returns Steam IDs and accurate player info for matchmaking servers.
     */
    protected function tryMMInfoUpdate(QueryResult $queryResult, ServerStatus $status, Server $server): bool
    {
        try {
            if (!$this->rconService->isAvailable($server)) {
                return false;
            }

            $mmInfo = $this->rconService->execute($server, 'mm_getinfo');

            if (empty($mmInfo)) {
                return false;
            }

            $mmData = json_decode($mmInfo, true);

            if (!is_array($mmData) || !isset($mmData['players']) || !isset($mmData['current_map'])) {
                return false;
            }

            $mmData['players'] = array_filter($mmData['players'], static fn($player) => $player['steamid'] !== '0');

            // Preserve game description from A2S query for CS2/CS:GO distinction
            if (isset($queryResult->additional['description'])) {
                $mmData['description'] = $queryResult->additional['description'];
            }

            $status->online = true;
            $status->players = count($mmData['players']);
            $status->max_players = $queryResult->maxPlayers;
            $status->map = $mmData['current_map'] ?? null;
            $status->game = self::GAME_CSGO;
            $status->setAdditional($mmData);
            $status->setPlayersData($mmData['players']);
            $status->touch();

            return true;
        } catch (Exception $e) {
            logs()->debug("mm_getinfo error for server {$server->ip}:{$server->port}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Format QueryResult player data to monitoring storage format.
     *
     * @return array<int, array{Name: string, Score: int, Time: float}>
     */
    protected function formatPlayersData(QueryResult $queryResult): array
    {
        $formatted = [];

        foreach ($queryResult->playersData as $player) {
            $formatted[] = [
                'Name' => $player['name'] ?? '',
                'Score' => $player['score'] ?? 0,
                'Time' => (int) ( $player['time'] ?? 0 ),
            ];
        }

        return $formatted;
    }

    protected function fetchEnabledServersWithStatuses(?array $servers = null): array
    {
        $servers ??= $this->cacheService->getEnabledServers();
        if (empty($servers)) {
            return [];
        }

        $statuses = $this->getLatestServerStatuses($servers);
        $result = [];

        foreach ($servers as $server) {
            $status = $statuses[$server->id] ?? $this->createDefaultStatus($server);

            $result[] = [
                'server' => $server,
                'status' => $status,
            ];
        }

        return $result;
    }

    protected function getLatestServerStatuses(array $servers): array
    {
        if (empty($servers)) {
            return [];
        }

        $serverIds = array_values(array_unique(array_map(static fn(Server $server) => (int) $server->id, $servers)));
        if (empty($serverIds)) {
            return [];
        }

        $yesterday = new DateTimeImmutable('-24 hours');

        $latestStatusMap = [];
        $select = ServerStatus::query()
            ->load('server')
            ->where('server_id', 'IN', new Parameter($serverIds))
            ->where('updated_at', '>=', $yesterday)
            ->orderBy('updated_at', 'DESC');

        $targetCount = count($serverIds);

        foreach ($select->getIterator(500) as $status) {
            $serverId = $status->server->id ?? null;

            if ($serverId === null || isset($latestStatusMap[$serverId])) {
                continue;
            }

            $latestStatusMap[$serverId] = $status;

            if (count($latestStatusMap) === $targetCount) {
                break;
            }
        }

        return $latestStatusMap;
    }

    protected function createDefaultStatus(Server $server): ServerStatus
    {
        $status = new ServerStatus();
        $status->server = $server;
        $status->online = false;

        return $status;
    }

    /**
     * Get server identifier for logging (safe for entities without ID)
     */
    protected function getServerIdentifier(Server $server): string
    {
        try {
            return (string) $server->id;
        } catch (Throwable $e) {
            return "{$server->ip}:{$server->port}";
        }
    }

    protected function saveStatus(ServerStatus $status): void
    {
        $dbTimezone = $this->detectDatabaseTimezone();
        $now = new DateTimeImmutable('now', $dbTimezone);

        $hourStart = $now->setTime((int) $now->format('H'), 0, 0);
        $hourEnd = $hourStart->modify('+1 hour');

        $existing = $this->findServerStatusInHourWindow($status->server->id, $hourStart, $hourEnd);

        if ($existing) {
            $this->copyServerStatusSnapshot($status, $existing);
            transaction($existing)->run();

            return;
        }

        try {
            transaction($status)->run();
        } catch (ConstrainException $e) {
            $this->fixAutoIncrement();

            $existing = $this->findServerStatusInHourWindow($status->server->id, $hourStart, $hourEnd);

            if ($existing !== null) {
                $this->copyServerStatusSnapshot($status, $existing);
                transaction($existing)->run();

                return;
            }

            transaction($status)->run();
        }
    }

    private function detectDatabaseTimezone(): DateTimeZone
    {
        static $tz = null;

        if ($tz !== null) {
            return $tz;
        }

        try {
            $db = app(\Flute\Core\Database\DatabaseConnection::class)->getDbal()->database();
            $driver = $db->getDriver();
            $pdo = method_exists($driver, 'getPDO') ? $driver->getPDO() : null;

            if ($pdo) {
                $result = $pdo->query("SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())")->fetchColumn();
                $offsetSeconds = (int) $result;
                $sign = $offsetSeconds >= 0 ? '+' : '-';
                $hours = abs(intdiv($offsetSeconds, 3600));
                $minutes = abs(intdiv($offsetSeconds % 3600, 60));
                $tz = new DateTimeZone(sprintf('%s%02d:%02d', $sign, $hours, $minutes));

                return $tz;
            }
        } catch (Throwable) {
        }

        $tz = new DateTimeZone('UTC');

        return $tz;
    }

    private function fixAutoIncrement(): void
    {
        try {
            $db = app(\Flute\Core\Database\DatabaseConnection::class)->getDbal()->database();
            $prefix = $db->getPrefix();
            $table = $prefix . 'server_statuses';

            $maxId = (int) $db->query("SELECT COALESCE(MAX(id), 0) FROM {$table}")->fetchColumn();
            $db->execute("ALTER TABLE {$table} AUTO_INCREMENT = " . ($maxId + 10));
        } catch (Throwable) {
        }
    }

    private function findServerStatusInHourWindow(int $serverId, DateTimeImmutable $hourStart, DateTimeImmutable $hourEnd): ?ServerStatus
    {
        $row = ServerStatus::query()
            ->where('server_id', $serverId)
            ->where('updated_at', '>=', $hourStart)
            ->where('updated_at', '<', $hourEnd)
            ->orderBy('updated_at', 'DESC')
            ->limit(1)
            ->fetchOne();

        return $row instanceof ServerStatus ? $row : null;
    }

    private function copyServerStatusSnapshot(ServerStatus $from, ServerStatus $onto): void
    {
        $onto->online = $from->online;
        $onto->players = $from->players;
        $onto->max_players = $from->max_players;
        $onto->map = $from->map;
        $onto->game = $from->game;
        $onto->players_data = $from->players_data;
        $onto->additional = $from->additional;
        $onto->touch();
    }

    protected function pruneOldStatuses(): void
    {
        $threshold = ( new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'UTC'))) )->modify(
            '-' . self::STATUS_RETENTION_DAYS . ' days',
        );

        db()->delete()->from('server_statuses')->where('updated_at', '<', $threshold)->run();
    }

    /**
     * Maximum allowed lock age in seconds before considering it stale.
     * Safety net for NFS / network filesystems where flock may not auto-release.
     */
    private const LOCK_TTL = 600;

    protected function setupScheduledMonitoring(): void
    {
        scheduler()->call(function (): void {
            $lockFile = storage_path('app/monitoring_cron.lock');

            // Ensure lock directory exists
            $lockDir = dirname($lockFile);
            if (!is_dir($lockDir)) {
                @mkdir($lockDir, 0755, true);
            }

            // Open with 'c' mode — create if missing, don't truncate existing
            $handle = @fopen($lockFile, 'c+');

            if ($handle === false) {
                logs()->warning('Monitoring cron: cannot open lock file');

                return;
            }

            // Primary mechanism: kernel-level flock.
            // Auto-released by OS if process dies/crashes — no stale locks on local FS.
            if (!@flock($handle, LOCK_EX | LOCK_NB)) {
                // flock failed — another process holds it. Check TTL as safety net
                // (covers NFS / network FS where flock may not auto-release).
                $holder = $this->readLockData($handle);
                $age = $holder ? time() - ($holder['acquired_at'] ?? 0) : 0;

                if ($holder && $age > self::LOCK_TTL && !$this->isProcessAlive($holder['pid'] ?? 0)) {
                    // Stale lock on broken FS: close, remove, reopen and re-acquire
                    logs()->warning("Monitoring cron: forcing stale lock (PID {$holder['pid']}, age {$age}s)");
                    @fclose($handle);
                    @unlink($lockFile);

                    $handle = @fopen($lockFile, 'c+');

                    if ($handle === false || !@flock($handle, LOCK_EX | LOCK_NB)) {
                        if (is_resource($handle)) {
                            @fclose($handle);
                        }

                        logs()->debug('Monitoring cron: skipped after stale lock cleanup');

                        return;
                    }
                } else {
                    @fclose($handle);
                    logs()->debug('Monitoring cron: skipped, previous execution still running');

                    return;
                }
            }

            // Lock acquired. Write diagnostics (PID + timestamp) for monitoring and TTL fallback.
            // The handle MUST stay open for the entire duration — closing releases flock.
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode([
                'pid' => getmypid(),
                'acquired_at' => time(),
            ]));
            fflush($handle);

            try {
                $this->updateAllServersStatus();
            } finally {
                @flock($handle, LOCK_UN);
                @fclose($handle);
                @unlink($lockFile);
            }
        })->everyMinute();
    }

    private function readLockData($handle): ?array
    {
        rewind($handle);
        $content = stream_get_contents($handle);

        if ($content === false || $content === '') {
            return null;
        }

        $data = @json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    private function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // Best: POSIX signal 0 — works on any Unix, no side effects
        if (function_exists('posix_kill')) {
            if (@posix_kill($pid, 0)) {
                return true;
            }

            // EPERM (1) = process exists but owned by another user
            // ESRCH (3) = no such process
            return function_exists('posix_get_last_error') && posix_get_last_error() === 1;
        }

        // Linux: procfs
        if (is_dir('/proc/' . $pid)) {
            return true;
        }

        // Unix without posix extension: shell fallback
        if (PHP_OS_FAMILY !== 'Windows') {
            $escaped = escapeshellarg((string) $pid);
            exec("kill -0 {$escaped} 2>/dev/null", $output, $retval);

            return $retval === 0;
        }

        // Windows
        $escaped = escapeshellarg((string) $pid);
        $out = @shell_exec("tasklist /FI \"PID eq {$escaped}\" /NH 2>NUL");

        return $out !== null && str_contains($out, (string) $pid);
    }

    protected function setupCacheBasedMonitoring(): void
    {
        $statusCacheKey = $this->cacheService->getServersStatusCacheKey();
        $countCacheKey = $this->cacheService->getServersCountCacheKey();
        $currentServerCount = count($this->cacheService->getEnabledServers());
        $cachedServerCount = $this->cacheService->getRaw($countCacheKey, 0);

        if ($currentServerCount !== $cachedServerCount) {
            $this->cacheService->delete($statusCacheKey);
            $this->cacheService->setRaw($countCacheKey, $currentServerCount, 999999999);
        }

        $this->cacheService->get(
            $statusCacheKey,
            function () use ($countCacheKey, $currentServerCount) {
                $this->cacheService->setRaw($countCacheKey, $currentServerCount, 999999999);
                $this->updateAllServersStatus();
            },
            $this->cacheService->getDefaultTtl(),
        );
    }

    protected function clearServersCache(): void
    {
        $this->servers = null;
        $this->serversSignature = '';
    }

    private function generateServersSignature(array $servers): string
    {
        if (empty($servers)) {
            return '';
        }

        $signatureParts = array_map(static function (Server $server) {
            $updatedTimestamp = $server->updatedAt instanceof DateTimeInterface
                ? $server->updatedAt->getTimestamp()
                : 0;

            return implode('|', [
                (int) $server->id,
                $server->ip,
                (int) $server->port,
                $server->mod,
                $server->enabled ? '1' : '0',
                $updatedTimestamp,
            ]);
        }, $servers);

        sort($signatureParts);

        return md5(json_encode($signatureParts));
    }

    /**
     * Determine if a CS 730 server is CS:GO (legacy) rather than CS2.
     * Returns true for CS:GO, false for CS2 or unknown.
     */
    public static function isCsgoLegacy(?ServerStatus $status): bool
    {
        if (!$status) {
            return false;
        }

        $additional = $status->getAdditional();
        $raw = $additional['description'] ?? '';
        $description = is_string($raw) ? $raw : '';

        // Primary check: description from A2S_INFO contains "Global Offensive"
        return stripos($description, 'Global Offensive') !== false;
    }

    /**
     * Get a human-readable game label for a server status.
     */
    public static function getGameLabel(?ServerStatus $status, ?Server $server = null): ?string
    {
        if (!$status) {
            return null;
        }

        $game = $status->game;
        $mod = $server->mod ?? $game;

        // CS2 / CS:GO distinction
        if ($mod === self::GAME_CSGO || $game === self::GAME_CSGO) {
            return self::isCsgoLegacy($status) ? 'CS:GO' : null;
        }

        return null;
    }

    private function convertSteamIdToDatabaseId(int $steamId): int
    {
        try {
            return (int) end(explode(':', steam()->steamid($steamId)->RenderSteam2()));
        } catch (Exception $e) {
            return 0;
        }
    }

    private function fetchAndAddSteamInfo(ServerStatus $status): void
    {
        $additional = $status->getAdditional();

        if (empty($additional['players'])) {
            return;
        }

        $players = &$additional['players'];

        $steamIds = array_filter(array_column($players, 'steamid'), static fn($id) => !empty($id) && $id !== '0');

        if (empty($steamIds)) {
            return;
        }

        try {
            $steamInfo = steam()->getUsersInfo($steamIds);

            $faceitBulk = [];
            try {
                if (class_exists('\\Flute\\Modules\\Monitoring\\Services\\FaceitRankService')) {
                    $faceitRankService = app()->get('\\Flute\\Modules\\Monitoring\\Services\\FaceitRankService');
                    $faceitBulk = $faceitRankService->getBulkFaceitPlayerInfo(array_values($steamIds));
                }
            } catch (Throwable $t) {
                // @mago-expect no-empty-catch-clause
            }

            foreach ($players as &$player) {
                $sid = $player['steamid'] ?? '';
                $player['steam_info'] = $steamInfo[$sid] ?? [];
                $player['faceit_info'] = $faceitBulk[$sid] ?? null;
            }

            $status->setAdditional($additional);
        } catch (Exception $e) {
            logs()->error("Error fetching Steam info for server {$status->server->id}: {$e->getMessage()}");
        }
    }
}
