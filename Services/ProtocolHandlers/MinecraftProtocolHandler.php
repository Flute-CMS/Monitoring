<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;

class MinecraftProtocolHandler implements ProtocolHandlerInterface
{
    protected const CONNECTION_TIMEOUT = 3; // seconds
    protected const GAME_MINECRAFT = 'minecraft';

    public function updateStatus(Server $server, ServerStatus $status): void
    {
        try {
            $socket = @fsockopen('udp://' . $server->ip, $server->port, $errno, $errstr, self::CONNECTION_TIMEOUT);

            if (!$socket) {
                throw new \Exception("Could not connect to Minecraft server");
            }

            $challengeTokenPacket = pack('c*', 0xFE, 0xFD, 0x09) . pack('N', 123);
            fwrite($socket, $challengeTokenPacket);

            $challengeResponse = fread($socket, 2048);
            if (strlen($challengeResponse) < 5 || $challengeResponse[0] != "\x09") {
                throw new \Exception("Invalid challenge response from Minecraft server");
            }
            $challengeToken = substr($challengeResponse, 5, -1);
            if (!is_numeric($challengeToken)) {
                throw new \Exception("Invalid challenge token format from Minecraft server");
            }
            $challengeToken = intval($challengeToken);

            $fullStatPacket = pack('c*', 0xFE, 0xFD, 0x00) . pack('N', 123) . pack('N', $challengeToken) . pack('c*', 0x00, 0x00, 0x00, 0x00);
            fwrite($socket, $fullStatPacket);

            $response = fread($socket, 4096);
            fclose($socket);

            if (!$response || strlen($response) < 11) {
                throw new \Exception("No or invalid full stat response from Minecraft server");
            }

            // Parsing logic based on https://wiki.vg/Query
            $data = substr($response, 11); // Remove padding and type
            $parts = explode("\x00\x00\x01player_\x00\x00", $data);
            $serverInfo = explode("\x00", $parts[0]);

            $infoMap = [];
            for ($i = 0; $i < count($serverInfo) - 1; $i += 2) {
                if (isset($serverInfo[$i + 1])) {
                    $infoMap[$serverInfo[$i]] = $serverInfo[$i + 1];
                }
            }

            $status->online = true;
            $status->players = isset($infoMap['numplayers']) ? (int)$infoMap['numplayers'] : 0;
            $status->max_players = isset($infoMap['maxplayers']) ? (int)$infoMap['maxplayers'] : 0;
            $status->map = isset($infoMap['map']) ? $infoMap['map'] : (isset($infoMap['world']) ? $infoMap['world'] : 'unknown');
            $status->game = self::GAME_MINECRAFT;

            $playersList = [];
            if (isset($parts[1])) {
                $playerNames = explode("\x00", substr($parts[1], 0, -2));
                foreach ($playerNames as $playerName) {
                    if (!empty($playerName)) {
                        $playersList[] = ['Name' => $playerName, 'Score' => 0, 'Time' => 0];
                    }
                }
            }

            $status->setPlayersData($playersList);
            $status->touch();
        } catch (\Exception $e) {
            logs()->debug("Minecraft status update error for server {$server->id}: {$e->getMessage()}");
            $status->online = false;
            $status->touch();
        }
    }
}
