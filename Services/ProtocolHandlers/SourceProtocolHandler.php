<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use xPaw\SourceQuery\SourceQuery;

class SourceProtocolHandler implements ProtocolHandlerInterface
{
    protected const CONNECTION_TIMEOUT = 2; // seconds
    protected const GAME_CSGO = '730';
    protected const GAME_CS16 = '10';

    public function updateStatus(Server $server, ServerStatus $status): void
    {
        $query = sq($server->ip, $server->port, self::CONNECTION_TIMEOUT, $this->getGameProtocolConstant($server->mod));

        try {
            if ($server->mod === self::GAME_CSGO && !empty($server->rcon)) {
                if ($this->tryMMInfoUpdate($query, $status, $server)) {
                    if (isset($query)) {
                        $query->Disconnect();
                    }
                    return;
                }
            }
        } catch (\Exception $e) {
            logs()->error("Error updating server status for {$server->ip}:{$server->port}: {$e->getMessage()}");
        }

        $this->updateStandardSourceInfo($query, $status);

        if (isset($query)) {
            $query->Disconnect();
        }
    }

    protected function getGameProtocolConstant(string $mod): int
    {
        return $mod === self::GAME_CS16 ? SourceQuery::GOLDSOURCE : SourceQuery::SOURCE;
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
                    $mmData['players'] = array_filter($mmData['players'], function ($player) {
                        return $player['steamid'] !== '0';
                    });

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
        } catch (\Exception $e) {
            logs()->debug("mm_getinfo error for server {$server->ip}:{$server->port}: {$e->getMessage()}");
            return false;
        }
        return false;
    }

    protected function updateStandardSourceInfo(SourceQuery $query, ServerStatus $status): void
    {
        $info = $query->GetInfo();
        $players = $query->GetPlayers();

        $players = array_filter($players, function ($player) {
            return $player['Time'] < 20000;
        });

        $status->online = true;
        $status->players = count($players);
        $status->max_players = $info['MaxPlayers'] ?? 0;
        $status->map = $info['Map'] ?? null;
        $status->game = $info['GameID'] ?? null;
        $status->setPlayersData($players);
        $status->touch();
    }
}
