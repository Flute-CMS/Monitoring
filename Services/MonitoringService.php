<?php

namespace Flute\Modules\Monitoring\Services;

use Cycle\Database\Injection\Parameter;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Services\DatabaseService;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\CS16ProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\Gta5ProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\MinecraftProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\ProtocolHandlerInterface;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\RustProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\SampProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\SourceProtocolHandler;
use Throwable;
use xPaw\SourceQuery\SourceQuery;

class MonitoringService
{
    public const STATUS_RETENTION_DAYS = 60;

    public const PROTOCOL_SOURCE = SourceQuery::SOURCE;

    public const PROTOCOL_GOLDSRC = SourceQuery::GOLDSOURCE;

    public const PROTOCOL_CS16 = 6;

    public const PROTOCOL_MINECRAFT = 2;

    public const PROTOCOL_SAMP = 3;

    public const PROTOCOL_GTA5 = 4;

    public const PROTOCOL_RUST = 5;

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

    private SourceProtocolHandler $sourceProtocolHandler;

    private CS16ProtocolHandler $cs16ProtocolHandler;

    private MinecraftProtocolHandler $minecraftProtocolHandler;

    private SampProtocolHandler $sampProtocolHandler;

    private Gta5ProtocolHandler $gta5ProtocolHandler;

    private RustProtocolHandler $rustProtocolHandler;

    private MonitoringCacheService $cacheService;

    private MapImageService $mapImageService;

    private MonitoringStatsService $statsService;

    private ?array $servers = null;

    private string $serversSignature = '';

    public function __construct(
        SourceProtocolHandler $sourceProtocolHandler,
        CS16ProtocolHandler $cs16ProtocolHandler,
        MinecraftProtocolHandler $minecraftProtocolHandler,
        SampProtocolHandler $sampProtocolHandler,
        Gta5ProtocolHandler $gta5ProtocolHandler,
        RustProtocolHandler $rustProtocolHandler,
        MonitoringCacheService $cacheService,
        MapImageService $mapImageService,
        MonitoringStatsService $statsService
    ) {
        $this->sourceProtocolHandler = $sourceProtocolHandler;
        $this->cs16ProtocolHandler = $cs16ProtocolHandler;
        $this->minecraftProtocolHandler = $minecraftProtocolHandler;
        $this->sampProtocolHandler = $sampProtocolHandler;
        $this->gta5ProtocolHandler = $gta5ProtocolHandler;
        $this->rustProtocolHandler = $rustProtocolHandler;
        $this->cacheService = $cacheService;
        $this->mapImageService = $mapImageService;
        $this->statsService = $statsService;
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
        return array_filter($this->getAllServers(), static fn($serverData) => $serverData['server']->enabled && ($serverData['status']->online === true));
    }

    public function getInactiveServers(): array
    {
        return array_filter($this->getAllServers(), static fn($serverData) => $serverData['server']->enabled && ($serverData['status']->online === false));
    }

    public function updateServerStatus(Server $server): ?ServerStatus
    {
        $status = new ServerStatus();
        $status->server = $server;
        $status->online = false;

        try {
            $protocol = $this->getGameProtocol($server->mod);
            $handler = $this->getProtocolHandler($protocol);

            if ($handler) {
                $handler->updateStatus($server, $status);
            } else {
                $serverId = $this->getServerIdentifier($server);
                logs()->warning("No protocol handler for server {$serverId} protocol {$protocol}");
            }
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
            $protocol = $this->getGameProtocol($mod);
            $handler = $this->getProtocolHandler($protocol);

            if ($handler) {
                $tempServer = new Server();
                $tempServer->ip = $ip;
                $tempServer->port = $port;
                $tempServer->mod = $mod;
                $tempServer->name = 'Test Server';
                $tempServer->enabled = true;

                $handler->updateStatus($tempServer, $status);
            }
        } catch (Exception $e) {
            $status->online = false;
        }

        return $status;
    }

    public function updateAllServersStatus(): void
    {
        $servers = $this->cacheService->getEnabledServers();

        $serversByProtocol = [];
        foreach ($servers as $server) {
            $protocol = $this->getGameProtocol($server->mod);
            if (!isset($serversByProtocol[$protocol])) {
                $serversByProtocol[$protocol] = [];
            }
            $serversByProtocol[$protocol][] = $server;
        }

        foreach ($serversByProtocol as $protocol => $protocolServers) {
            $handler = $this->getProtocolHandler($protocol);
            if (!$handler) {
                continue;
            }

            // Process servers in batches to avoid memory and timeout issues
            $batches = array_chunk($protocolServers, self::BATCH_SIZE);

            foreach ($batches as $batch) {
                $this->processBatch($batch, $handler);

                // Clear entity manager to free memory after each batch
                if (method_exists(db(), 'getEntityManager')) {
                    try {
                        db()->getEntityManager()->clear();
                    } catch (Throwable $e) {
                        // Ignore
                    }
                }
            }
        }

        $this->pruneOldStatuses();
        $this->clearServersCache();
    }

    /**
     * Process a batch of servers
     */
    protected function processBatch(array $servers, ProtocolHandlerInterface $handler): void
    {
        foreach ($servers as $server) {
            try {
                $status = new ServerStatus();
                $status->server = $server;
                $status->online = false;

                $handler->updateStatus($server, $status);
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

        return $this->cacheService->get(
            $cacheKey,
            fn() => $this->statsService->getTotalPlayersCount(),
            60 // 1 minute
        );
    }

    public function getServerStats(Server $server): ?array
    {
        $dbMod = app(DatabaseService::class)->getConnectionInfoByServerId($server->id, [
            'LR',
        ]);

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
        $tableName = $dbMod['connection']->getAdditional()['table_name'] ?? "base";

        if (!config('database.databases.' . $driver->dbname . '.prefix')) {
            $prefix = 'lvl_';
        }

        $playerRank = db($driver->dbname)->select()->from($prefix . $tableName)->where('steam', 'like', '%' . $this->convertSteamIdToDatabaseId($steamId))->fetchAll();

        if (empty($playerRank)) {
            return '<img src="' . asset('assets/img/ranks/default/0.webp') . '" alt="0" loading="lazy">';
        }

        $playerRank = $playerRank[0];

        if (method_exists($server, 'getRank')) {
            return $server->getRank($playerRank['rank'] ?? 0, $playerRank['value'] ?? 0);
        }

        return '<img src="' . asset('assets/img/ranks/' . ($server->ranks ?? 'default') . '/' . (!empty($playerRank['rank']) ? $playerRank['rank'] : '0') . '.' . ($server->ranks_format ?? 'webp')) . '" alt="' . $playerRank['rank'] . '" loading="lazy">';
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

    protected function getGameProtocol(string $mod): int
    {
        $protocolMap = [
            self::GAME_CSGO => self::PROTOCOL_SOURCE,
            self::GAME_CSS => self::PROTOCOL_SOURCE,
            self::GAME_TF2 => self::PROTOCOL_SOURCE,
            self::GAME_L4D2 => self::PROTOCOL_SOURCE,
            '1002' => self::PROTOCOL_SOURCE,
            '2400' => self::PROTOCOL_SOURCE,
            self::GAME_GARRYSMOD => self::PROTOCOL_SOURCE,
            '221100' => self::PROTOCOL_SOURCE,
            '17710' => self::PROTOCOL_SOURCE,
            '70000' => self::PROTOCOL_SOURCE,
            '107410' => self::PROTOCOL_SOURCE,
            '115300' => self::PROTOCOL_SOURCE,
            '162107' => self::PROTOCOL_SOURCE,
            '211820' => self::PROTOCOL_SOURCE,
            '244850' => self::PROTOCOL_SOURCE,
            '304930' => self::PROTOCOL_SOURCE,
            '251570' => self::PROTOCOL_SOURCE,
            '282440' => self::PROTOCOL_SOURCE,
            '346110' => self::PROTOCOL_SOURCE,
            '108600' => self::PROTOCOL_SOURCE,
            '252490' => self::PROTOCOL_RUST, // Rust
            'rust' => self::PROTOCOL_RUST,
            self::GAME_CS16 => self::PROTOCOL_CS16,
            'all_hl_games_mods' => self::PROTOCOL_CS16,
            self::GAME_MINECRAFT => self::PROTOCOL_MINECRAFT,
            self::GAME_GTA5 => self::PROTOCOL_GTA5,
            self::GAME_SAMP => self::PROTOCOL_SAMP,
            self::GAME_RUST => self::PROTOCOL_RUST,
        ];

        return $protocolMap[$mod] ?? self::PROTOCOL_SOURCE;
    }

    protected function getProtocolHandler(int $protocol): ?ProtocolHandlerInterface
    {
        switch ($protocol) {
            case self::PROTOCOL_SOURCE:
            case self::PROTOCOL_GOLDSRC:
                return $this->sourceProtocolHandler;
            case self::PROTOCOL_CS16:
                return $this->cs16ProtocolHandler;
            case self::PROTOCOL_MINECRAFT:
                return $this->minecraftProtocolHandler;
            case self::PROTOCOL_SAMP:
                return $this->sampProtocolHandler;
            case self::PROTOCOL_GTA5:
                return $this->gta5ProtocolHandler;
            case self::PROTOCOL_RUST:
                return $this->rustProtocolHandler;
            default:
                return null;
        }
    }

    protected function setServerOffline(ServerStatus $status): void
    {
        $status->online = false;
        $status->touch();
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
        $timezone = new DateTimeZone(config('app.timezone', 'UTC'));
        $now = new DateTimeImmutable('now', $timezone);

        $hourStart = $now->setTime((int) $now->format('H'), 0, 0);
        $hourEnd = $hourStart->modify('+1 hour');

        $existing = ServerStatus::query()
            ->where('server_id', $status->server->id)
            ->where('updated_at', '>=', $hourStart)
            ->where('updated_at', '<', $hourEnd)
            ->orderBy('updated_at', 'DESC')
            ->limit(1)
            ->fetchOne();

        if ($existing) {
            $existing->online = $status->online;
            $existing->players = $status->players;
            $existing->max_players = $status->max_players;
            $existing->map = $status->map;
            $existing->game = $status->game;
            $existing->players_data = $status->players_data;
            $existing->additional = $status->additional;
            $existing->touch();
            transaction($existing)->run();
        } else {
            transaction($status)->run();
        }
    }

    protected function pruneOldStatuses(): void
    {
        $threshold = (new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'UTC'))))
            ->modify('-' . self::STATUS_RETENTION_DAYS . ' days');

        db()->delete()
            ->from('server_statuses')
            ->where('updated_at', '<', $threshold)
            ->run();
    }

    protected function setupScheduledMonitoring(): void
    {
        scheduler()->call(function (): void {
            $lockFile = storage_path('app/monitoring_cron.lock');
            $handle = @fopen($lockFile, 'c+');

            if ($handle === false) {
                logs()->warning('Monitoring cron: failed to open lock file');
                return;
            }

            // Non-blocking lock - if another instance is running, skip this execution
            if (!@flock($handle, LOCK_EX | LOCK_NB)) {
                @fclose($handle);
                logs()->debug('Monitoring cron: skipped, previous execution still running');
                return;
            }

            try {
                $this->updateAllServersStatus();
            } finally {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }
        })->everyMinute();
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

        $this->cacheService->get($statusCacheKey, function () use ($countCacheKey, $currentServerCount) {
            $this->cacheService->setRaw($countCacheKey, $currentServerCount, 999999999);
            $this->updateAllServersStatus();
        }, $this->cacheService->getDefaultTtl());
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
            $updatedTimestamp = ($server->updatedAt instanceof DateTimeInterface)
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

        $steamIds = array_column($players, 'steamid');

        if (empty($steamIds)) {
            return;
        }

        try {
            $steamInfo = steam()->getUsersInfo($steamIds);

            foreach ($players as &$player) {
                $player['steam_info'] = $steamInfo[$player['steamid']] ?? [];

                try {
                    if (class_exists('\\Flute\\Modules\\Monitoring\\Services\\FaceitRankService')) {
                        $faceitRankService = app()->get('\\Flute\\Modules\\Monitoring\\Services\\FaceitRankService');
                        $player['faceit_info'] = $faceitRankService->getFaceitPlayerInfo((int) $player['steamid']);
                    }
                } catch (Throwable $t) {
                }
            }

            $status->setAdditional($additional);
        } catch (Exception $e) {
            logs()->error("Error fetching Steam info for server {$status->server->id}: {$e->getMessage()}");
        }
    }
}
