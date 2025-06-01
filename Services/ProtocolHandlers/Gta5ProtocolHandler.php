<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;

class Gta5ProtocolHandler implements ProtocolHandlerInterface
{
    protected const CONNECTION_TIMEOUT = 3; // seconds
    protected const GAME_GTA5 = 'gta5';

    public function updateStatus(Server $server, ServerStatus $status): void
    {
        try {
            $url = "http://{$server->ip}:{$server->port}/info.json";

            $context = stream_context_create([
                'http' => [
                    'timeout' => self::CONNECTION_TIMEOUT,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if (!$response) {
                throw new \Exception("Could not connect to GTA5 server or empty response");
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON response from GTA5 server: " . json_last_error_msg());
            }

            if (!$data) {
                throw new \Exception("Empty or invalid data from GTA5 server after JSON decode");
            }

            $status->online = true;
            $status->players = $data['clients'] ?? ($data['players'] ?? 0);
            $status->max_players = $data['sv_maxclients'] ?? ($data['maxclients'] ?? 0);
            $status->map = $data['mapname'] ?? 'unknown';
            $status->game = self::GAME_GTA5;

            $playersList = [];
            if (isset($data['players']) && is_array($data['players'])) {
                foreach ($data['players'] as $player) {
                    $playersList[] = [
                        'Name' => $player['name'] ?? 'Unknown',
                        'Score' => $player['score'] ?? 0,
                        'Time' => $player['ping'] ?? 0,
                    ];
                }
                if (isset($data['clients']) && is_numeric($data['clients']) && count($playersList) > 0 && $data['clients'] != count($playersList)) {
                    $status->players = count($playersList);
                }
            } else if (isset($data['clients']) && is_numeric($data['clients'])) {
                $status->players = $data['clients'];
            }


            $status->setPlayersData($playersList);
            $status->touch();
        } catch (\Exception $e) {
            logs()->debug("GTA5 status update error for server {$server->id}: {$e->getMessage()}");
            $status->online = false;
            $status->touch();
        }
    }
}
