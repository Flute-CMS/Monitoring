<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Flute\Modules\Monitoring\Services\Protocols\CS16Protocol;

class CS16ProtocolHandler implements ProtocolHandlerInterface
{
    protected const CONNECTION_TIMEOUT = 10; // seconds

    protected const GAME_CS16 = '10';

    public function updateStatus(Server $server, ServerStatus $status): void
    {
        $protocol = new CS16Protocol($server->ip, $server->port);

        try {
            if (!$protocol->connect()) {
                $this->setServerOffline($status);

                return;
            }

            $this->updateCS16Info($protocol, $status, $server);

        } catch (Exception $e) {
            logs()->error("Error updating CS 1.6 server status for {$server->ip}:{$server->port}: {$e->getMessage()}");
            $this->setServerOffline($status);
        } finally {
            $protocol->disconnect();
        }
    }

    protected function updateCS16Info(CS16Protocol $protocol, ServerStatus $status, Server $server): void
    {
        $info = $protocol->getInfo();
        $players = [];
        $playerCount = 0;

        if ($info === null) {
            logs()->debug("Failed to get server info for CS 1.6 server {$server->ip}:{$server->port}");
            $this->setServerOffline($status);

            return;
        }

        try {
            $players = $protocol->getPlayers();

            if (is_array($players) && !empty($players)) {
                $players = array_filter($players, static fn ($player) => !empty($player['name']) && trim($player['name']) !== '');
                $playerCount = count($players);
            } else {
                $playerCount = $info['players'] ?? 0;
                $players = [];
                logs()->debug("GetPlayers() returned empty/invalid data for CS 1.6 {$server->ip}:{$server->port}, using fallback count: {$playerCount}");
            }
        } catch (Exception $e) {
            logs()->debug("GetPlayers() failed for CS 1.6 server {$server->ip}:{$server->port}: {$e->getMessage()}");
            $playerCount = $info['players'] ?? 0;
            $players = [];
        }

        $status->online = true;
        $status->players = $playerCount;
        $status->max_players = $info['max_players'] ?? 0;
        $status->map = $info['map'] ?? null;
        $status->game = self::GAME_CS16;

        $formattedPlayers = [];
        foreach ($players as $player) {
            $formattedPlayers[] = [
                'Name' => $player['name'] ?? '',
                'Score' => $player['score'] ?? 0,
                'Time' => $player['time'] ?? 0,
                'Index' => $player['index'] ?? 0,
            ];
        }

        $status->setPlayersData($formattedPlayers);
        $status->touch();
    }

    protected function setServerOffline(ServerStatus $status): void
    {
        $status->online = false;
        $status->players = 0;
        $status->max_players = 0;
        $status->map = null;
        $status->game = self::GAME_CS16;
        $status->setPlayersData([]);
        $status->touch();
    }
}
