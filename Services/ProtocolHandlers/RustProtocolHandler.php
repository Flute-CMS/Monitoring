<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;

/**
 * @link https://developer.valvesoftware.com/wiki/Server_queries
 */
class RustProtocolHandler implements ProtocolHandlerInterface
{
    private const CONNECTION_TIMEOUT = 3;

    private const GAME_RUST = 'rust';

    private const A2S_INFO = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";

    private const A2S_PLAYER_PREFIX = "\xFF\xFF\xFF\xFFU";

    private const A2S_PLAYER_CHALLENGE_FAKE = "\xFF\xFF\xFF\xFF";

    private function readCString(string &$buffer): string
    {
        $pos = strpos($buffer, "\x00");
        if ($pos === false) {
            $str    = $buffer;
            $buffer = '';
            return $str;
        }

        $str    = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 1);
        return $str;
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(Server $server, ServerStatus $status): void
    {
        $socket = null;

        try {
            $socket = @fsockopen(
                'udp://' . $server->ip,
                (int)$server->port,
                $errno,
                $errstr,
                self::CONNECTION_TIMEOUT
            );

            if (!$socket) {
                throw new \RuntimeException("Не удалось открыть UDP-сокет: {$errstr} ({$errno})");
            }
            stream_set_timeout($socket, self::CONNECTION_TIMEOUT);

            fwrite($socket, self::A2S_INFO);
            $infoRaw = fread($socket, 4096);

            if (!$infoRaw || strlen($infoRaw) < 6 || $infoRaw[4] !== "\x49") {
                throw new \RuntimeException('Некорректный ответ A2S_INFO');
            }

            $payload = substr($infoRaw, 5);

            $protocol       = ord($payload[0]);
            $payload        = substr($payload, 1);

            $serverName     = $this->readCString($payload);
            $currentMap     = $this->readCString($payload);
            $this->readCString($payload); // «rust»
            $this->readCString($payload);

            $appid          = unpack('v', substr($payload, 0, 2))[1];
            $payload        = substr($payload, 2);

            $players        = ord($payload[0]);
            $maxPlayers     = ord($payload[1]);

            $playersList    = [];

            fwrite($socket, self::A2S_PLAYER_PREFIX . self::A2S_PLAYER_CHALLENGE_FAKE);
            $challengeResp = fread($socket, 4096);

            if ($challengeResp && strlen($challengeResp) >= 9 && $challengeResp[4] === "\x41") {
                $challenge = substr($challengeResp, 5, 4);

                fwrite($socket, self::A2S_PLAYER_PREFIX . $challenge);
                $playersRaw = fread($socket, 65535);

                if ($playersRaw && $playersRaw[4] === "\x44") {
                    $payload       = substr($playersRaw, 5);
                    $playersInResp = ord($payload[0]);
                    $payload       = substr($payload, 1);

                    for ($i = 0; $i < $playersInResp; $i++) {
                        if (strlen($payload) < 9) {
                            break;
                        }
                        $payload = substr($payload, 1);
                        $name        = $this->readCString($payload);
                        $score       = unpack('l', substr($payload, 0, 4))[1];
                        $duration    = unpack('f', substr($payload, 4, 4))[1];
                        $payload     = substr($payload, 8);

                        $playersList[] = [
                            'Name'  => $name,
                            'Score' => $score,
                            'Time'  => (int)$duration,
                        ];
                    }
                }
            }

            $status->online       = true;
            $status->players      = $players;
            $status->max_players  = $maxPlayers;
            $status->map          = $currentMap ?: 'unknown';
            $status->game         = self::GAME_RUST;

            $status->setPlayersData($playersList);
            $status->touch();
        } catch (\Throwable $e) {
            logs()->debug("Rust status update error for server {$server->id}: {$e->getMessage()}");

            $status->online = false;
            $status->touch();
        } finally {
            if ($socket && is_resource($socket)) {
                fclose($socket);
            }
        }
    }
}
