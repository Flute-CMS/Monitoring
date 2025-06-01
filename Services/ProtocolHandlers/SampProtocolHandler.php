<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;

class SampProtocolHandler implements ProtocolHandlerInterface
{
    protected const CONNECTION_TIMEOUT = 3; // seconds
    protected const GAME_SAMP = 'samp';

    public function updateStatus(Server $server, ServerStatus $status): void
    {
        try {
            $socket = @fsockopen('udp://' . $server->ip, $server->port, $errno, $errstr, self::CONNECTION_TIMEOUT);

            if (!$socket) {
                throw new \Exception("Could not connect to SAMP server");
            }

            $query = "SAMP";
            $ipParts = explode('.', $server->ip);
            foreach ($ipParts as $part) {
                $query .= chr((int)$part);
            }
            $query .= chr($server->port & 0xFF);
            $query .= chr($server->port >> 8 & 0xFF);
            $query .= 'i';

            fwrite($socket, $query);

            $header = fread($socket, 11);
            if (!$header || strlen($header) < 11) {
                fclose($socket);
                throw new \Exception("Invalid or incomplete header from SAMP server");
            }

            if (substr($header, 0, 4) !== 'SAMP') {
                fclose($socket);
                throw new \Exception("Invalid SAMP signature in response");
            }

            $playersData = unpack('v', substr($header, 5, 2));
            $status->players = $playersData[1];

            $maxPlayersData = unpack('v', substr($header, 7, 2));
            $status->max_players = $maxPlayersData[1];

            $hostnameLenBytes = fread($socket, 4);
            $hostnameLenBytes = fread($socket, 4);
            if (!$hostnameLenBytes || strlen($hostnameLenBytes) < 4) {
                fclose($socket);
                throw new \Exception("Could not read hostname length");
            }
            $hostnameLen = unpack('L', $hostnameLenBytes)[1];
            $hostname = fread($socket, $hostnameLen);

            $modeLenBytes = fread($socket, 4);
            if (!$modeLenBytes || strlen($modeLenBytes) < 4) {
                fclose($socket);
                throw new \Exception("Could not read mode length");
            }
            $modeLen = unpack('L', $modeLenBytes)[1];
            $mode = fread($socket, $modeLen);

            fclose($socket);

            $status->online = true;
            $status->map = $mode;
            $status->game = self::GAME_SAMP;

            $status->setPlayersData([]);
            $status->touch();
        } catch (\Exception $e) {
            logs()->debug("SAMP status update error for server {$server->id}: {$e->getMessage()}");
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            $status->online = false;
            $status->touch();
        }
    }
}
