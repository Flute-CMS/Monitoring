<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Throwable;
use xPaw\SourceQuery\SourceQuery;

class SourceProtocolHandler implements ProtocolHandlerInterface
{
    protected const CONNECTION_TIMEOUT = 10; // seconds

    protected const GAME_CSGO = '730';

    protected const GAME_CS16 = '10';

    public function updateStatus(Server $server, ServerStatus $status): void
    {
        // CS 1.6 is now handled by CS16ProtocolHandler
        if ($server->mod === self::GAME_CS16) {
            logs()->warning("CS 1.6 server {$server->ip}:{$server->port} should be handled by CS16ProtocolHandler, not SourceProtocolHandler");

            return;
        }

        $query = null;
        $engine = $this->getGameProtocolConstant($server->mod);
        $settings = method_exists($server, 'getSettings') ? $server->getSettings() : [];
        $candidates = $this->buildPortCandidates($server, $settings);

        foreach ($candidates as $candidatePort) {
            try {
                $query = sq($server->ip, $candidatePort, self::CONNECTION_TIMEOUT, $engine);

                break;
            } catch (Throwable $e) {
                $query = null;
            }
        }

        if ($query === null) {
            logs()->debug("Source query connect failed for {$server->ip}:{$server->port} (tried: " . implode(',', $candidates) . ')');
            $status->online = false;
            $status->touch();

            return;
        }

        try {
            if ($server->mod === self::GAME_CSGO && !empty($server->rcon)) {
                if ($this->tryMMInfoUpdate($query, $status, $server)) {
                    if (isset($query)) {
                        $query->Disconnect();
                    }

                    return;
                }
            }
        } catch (Exception $e) {
            logs()->error("Error updating server status for {$server->ip}:{$server->port}: {$e->getMessage()}");
        }

        $this->updateStandardSourceInfo($query, $status, $server);

        if (isset($query)) {
            $query->Disconnect();
        }
    }

    protected function getGameProtocolConstant(string $mod): int
    {
        // CS 1.6 is handled by CS16ProtocolHandler, not here
        return SourceQuery::SOURCE;
    }

    protected function buildPortCandidates(Server $server, array $settings): array
    {
        $candidates = [];

        if (isset($settings['query_port']) && (int)$settings['query_port'] > 0) {
            $candidates[] = (int)$settings['query_port'];
        }

        $candidates[] = (int)$server->port;

        // if (in_array($server->mod, $this->modsWithPortPlusOne(), true)) {
        //     $candidates[] = (int)$server->port + 1;
        // }

        return array_unique(array_filter($candidates, static fn ($port) => $port > 0));
    }

    protected function modsWithPortPlusOne(): array
    {
        return [
            '221100', // DayZ
            '251570', // 7 Days to Die
        ];
    }

    protected function tryMMInfoUpdate(SourceQuery $query, ServerStatus $status, Server $server): bool
    {
        try {
            $query->SetRconPassword($server->rcon);

            $mmInfo = $query->Rcon('mm_getinfo');
            $info = $query->GetInfo();

            if (!empty($mmInfo)) {
                $mmData = json_decode($mmInfo, true);

                if (is_array($mmData) && isset($mmData['players']) && isset($mmData['current_map'])) {
                    $mmData['players'] = array_filter($mmData['players'], static fn ($player) => $player['steamid'] !== '0');

                    $status->online = true;
                    $status->players = count($mmData['players']);
                    $status->max_players = $info['MaxPlayers'] ?? 10;
                    $status->map = $mmData['current_map'] ?? null;
                    $status->game = self::GAME_CSGO;
                    $status->setAdditional($mmData);
                    $status->setPlayersData($mmData['players']);
                    $status->touch();

                    return true;
                }
            }
        } catch (Exception $e) {
            logs()->debug("mm_getinfo error for server {$server->ip}:{$server->port}: {$e->getMessage()}");

            return false;
        }

        return false;
    }

    protected function updateStandardSourceInfo(SourceQuery $query, ServerStatus $status, Server $server): void
    {
        $info = $query->GetInfo();
        $players = [];
        $playerCount = 0;

        try {
            $players = $query->GetPlayers();

            if (is_array($players) && !empty($players)) {
                if ($server->mod === self::GAME_CS16) {
                    $playerCount = count($players);
                } else {
                    $players = array_filter($players, static fn ($player) => isset($player['Time']) && $player['Time'] < 20000);
                    $playerCount = count($players);
                }
            } else {
                // GetPlayers() returned empty array or non-array, fallback to GetInfo()
                $playerCount = $info['Players'] ?? 0;
                $players = [];
                logs()->debug("GetPlayers() returned empty/invalid data for {$server->ip}:{$server->port}, using fallback count: {$playerCount}");
            }
        } catch (Exception $e) {
            logs()->debug("GetPlayers() failed for server {$server->ip}:{$server->port}: {$e->getMessage()}");
            $playerCount = $info['Players'] ?? 0;
            $players = [];
        }

        $status->online = true;
        $status->players = $playerCount;
        $status->max_players = $info['MaxPlayers'] ?? 0;
        $status->map = $info['Map'] ?? null;
        $status->game = $info['GameID'] ?? null;
        $status->setPlayersData($players);
        $status->touch();
    }
}
