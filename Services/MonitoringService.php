<?php

namespace Flute\Modules\Monitoring\Services;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Services\DatabaseService;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\Gta5ProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\MinecraftProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\SampProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\SourceProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\RustProtocolHandler;
use Flute\Modules\Monitoring\Services\ProtocolHandlers\ProtocolHandlerInterface;
use Flute\Modules\Monitoring\Services\MonitoringCacheService;
use Flute\Modules\Monitoring\Services\MapImageService;
use Flute\Modules\Monitoring\Services\MonitoringStatsService;
use xPaw\SourceQuery\SourceQuery;

class MonitoringService
{
    public const STATUS_RETENTION_DAYS = 60;

    public const PROTOCOL_SOURCE = SourceQuery::SOURCE;
    public const PROTOCOL_GOLDSRC = SourceQuery::GOLDSOURCE;
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

    private SourceProtocolHandler $sourceProtocolHandler;
    private MinecraftProtocolHandler $minecraftProtocolHandler;
    private SampProtocolHandler $sampProtocolHandler;
    private Gta5ProtocolHandler $gta5ProtocolHandler;
    private RustProtocolHandler $rustProtocolHandler;
    private MonitoringCacheService $cacheService;
    private MapImageService $mapImageService;
    private MonitoringStatsService $statsService;

    private array $servers = [];

    public function __construct(
        SourceProtocolHandler $sourceProtocolHandler,
        MinecraftProtocolHandler $minecraftProtocolHandler,
        SampProtocolHandler $sampProtocolHandler,
        Gta5ProtocolHandler $gta5ProtocolHandler,
        RustProtocolHandler $rustProtocolHandler,
        MonitoringCacheService $cacheService,
        MapImageService $mapImageService,
        MonitoringStatsService $statsService
    ) {
        $this->sourceProtocolHandler = $sourceProtocolHandler;
        $this->minecraftProtocolHandler = $minecraftProtocolHandler;
        $this->sampProtocolHandler = $sampProtocolHandler;
        $this->gta5ProtocolHandler = $gta5ProtocolHandler;
        $this->rustProtocolHandler = $rustProtocolHandler;
        $this->cacheService = $cacheService;
        $this->mapImageService = $mapImageService;
        $this->statsService = $statsService;
    }

    public function getAllServers(): array
    {
        if (empty($this->servers)) {
            $this->servers = $this->fetchEnabledServersWithStatuses();
        }

        return $this->servers;
    }

    protected function fetchEnabledServersWithStatuses(): array
    {
        $servers = Server::findAll(['enabled' => true]);
        $statuses = $this->getLatestServerStatuses();
        $result = [];

        foreach ($servers as $server) {
            $status = $statuses[$server->id] ?? $this->createDefaultStatus($server);

            $result[] = [
                'server' => $server,
                'status' => $status
            ];
        }

        return $result;
    }

    protected function getLatestServerStatuses(): array
    {
        $servers = Server::findAll(['enabled' => true]);
        if (empty($servers)) {
            return [];
        }

        $serverIds = array_map(fn(Server $s) => $s->id, $servers);

        $statusQuery = ServerStatus::query()
            ->where('server.id', 'IN', $serverIds)
            ->orderBy('updated_at', 'DESC')
            ->limit(count($serverIds));

        if (!config('app.cron_mode')) {
            $ttl = $this->cacheService->getDefaultTtl();
            $threshold = (new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone', 'UTC'))))
                ->modify("-{$ttl} seconds");
            $statusQuery->where('updated_at', '>=', $threshold);
        }

        $allRelevantStatuses = $statusQuery->fetchAll();
        $latestStatusMap = [];

        foreach ($allRelevantStatuses as $status) {
            $serverId = $status->server->id;
            if (!isset($latestStatusMap[$serverId])) {
                $latestStatusMap[$serverId] = $status;
            }
        }

        return $latestStatusMap;
    }

    protected function createDefaultStatus(Server $server): ServerStatus
    {
        $status = new ServerStatus();
        $status->server = $server;
        transaction($status)->run();
        return $status;
    }

    public function getServerById(int $id): ?array
    {
        if (!$id) return null;
        $server = Server::findByPK($id);

        if (!$server || !$server->enabled) return null;

        $status = ServerStatus::query()
            ->where('server.id', $id)
            ->orderBy('updated_at', 'DESC')
            ->fetchOne() ?? $this->createDefaultStatus($server);

        return ['server' => $server, 'status' => $status];
    }

    public function getActiveServers(): array
    {
        return array_filter($this->getAllServers(), function ($serverData) {
            return $serverData['server']->enabled;
        });
    }

    public function getInactiveServers(): array
    {
        return array_filter($this->getAllServers(), function ($serverData) {
            return $serverData['server']->enabled && !($serverData['status']->online ?? true);
        });
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
            '17710' => self::PROTOCOL_SOURCE,
            '70000' => self::PROTOCOL_SOURCE,
            '107410' => self::PROTOCOL_SOURCE,
            '115300' => self::PROTOCOL_SOURCE,
            '162107' => self::PROTOCOL_SOURCE,
            '211820' => self::PROTOCOL_SOURCE,
            '244850' => self::PROTOCOL_SOURCE,
            '304930' => self::PROTOCOL_SOURCE,
            '251570' => self::PROTOCOL_SOURCE,
            '252490' => self::PROTOCOL_SOURCE,
            '282440' => self::PROTOCOL_SOURCE,
            '346110' => self::PROTOCOL_SOURCE,
            '108600' => self::PROTOCOL_SOURCE,
            '252490' => self::PROTOCOL_RUST, // Rust
            'rust' => self::PROTOCOL_RUST,
            self::GAME_CS16 => self::PROTOCOL_GOLDSRC,
            'all_hl_games_mods' => self::PROTOCOL_GOLDSRC,
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

    public function updateServerStatus(Server $server): ?ServerStatus
    {
        $status = new ServerStatus();
        $status->server = $server;

        try {
            $protocol = $this->getGameProtocol($server->mod);
            $handler = $this->getProtocolHandler($protocol);

            if ($handler) {
                $handler->updateStatus($server, $status);
            } else {
                logs()->warning("No protocol handler for server {$server->id} protocol {$protocol}");
            }
        } catch (\Exception $e) {
            logs()->error("Error updating server status for {$server->id}: {$e->getMessage()}");
            $this->setServerOffline($status);
        }

        $this->saveStatus($status);

        return $status;
    }

    protected function setServerOffline(ServerStatus $status): void
    {
        $status->online = false;
        $status->touch();
    }

    protected function saveStatus(ServerStatus $status): void
    {
        transaction($status)->run();
    }

    public function updateAllServersStatus(): void
    {
        $servers = Server::findAll(['enabled' => true]);
        foreach ($servers as $server) {
            $this->updateServerStatus($server);
        }

        $this->pruneOldStatuses();
    }

    protected function pruneOldStatuses(): void
    {
        $threshold = (new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone', 'UTC'))))
            ->modify('-' . self::STATUS_RETENTION_DAYS . ' days');

        db()->delete()->from('server_statuses')->where('updated_at', '<', $threshold)->run();
    }

    public function setupCron(): void
    {
        if (config('app.cron_mode')) {
            $this->setupScheduledMonitoring();
        } else {
            $this->setupCacheBasedMonitoring();
        }
    }

    protected function setupScheduledMonitoring(): void
    {
        scheduler()->call(fn() => $this->updateAllServersStatus())->everyMinute();
    }

    protected function setupCacheBasedMonitoring(): void
    {
        $statusCacheKey = $this->cacheService->getServersStatusCacheKey();
        $countCacheKey = $this->cacheService->getServersCountCacheKey();
        $currentServerCount = count(Server::findAll(['enabled' => true]));
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

    public function getMapPreview(?ServerStatus $status): string
    {
        return $this->mapImageService->getMapPreview($status);
    }

    public function getTotalPlayersCount(): array
    {
        return $this->statsService->getTotalPlayersCount();
    }

    public function getServerStats(Server $server): ?array
    {
        $dbMod = app(DatabaseService::class)->getConnectionInfoByServerId($server->id, [
            'LR'
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

        return 'assets/img/ranks/' . ($server->ranks ?? 'default') . '/' . (!empty($playerRank['rank']) ? $playerRank['rank'] : '0') . '.' . ($server->ranks_format ?? 'webp');
    }

    private function convertSteamIdToDatabaseId(int $steamId): int
    {
        try {
            return (int) substr(steam()->steamid($steamId)->RenderSteam2(), -11);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
