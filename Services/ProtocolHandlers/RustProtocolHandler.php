<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use RuntimeException;
use Throwable;

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

    /**
     * {@inheritDoc}
     */
    public function updateStatus(Server $server, ServerStatus $status): void
    {
        $socket = null;

        try {
            $settings = method_exists($server, 'getSettings') ? $server->getSettings() : [];
            $queryPort = isset($settings['query_port']) ? (int)$settings['query_port'] : (int)$server->port;

            $socket = @fsockopen(
                'udp://' . $server->ip,
                $queryPort,
                $errno,
                $errstr,
                self::CONNECTION_TIMEOUT
            );

            if (!$socket) {
                throw new RuntimeException("Не удалось открыть UDP-сокет: {$errstr} ({$errno})");
            }
            stream_set_timeout($socket, self::CONNECTION_TIMEOUT);

            $infoRaw = $this->sendAndRead($socket, self::A2S_INFO, 4096);
            [$infoType, $payload] = $this->parseResponse($infoRaw);

            if ($infoType === 0x41 && strlen($payload) >= 4) {
                $challenge = substr($payload, 0, 4);
                $infoRaw = $this->sendAndRead($socket, self::A2S_INFO . $challenge, 4096);
                [$infoType, $payload] = $this->parseResponse($infoRaw);
            }

            if ($infoType !== 0x49 || $payload === '') {
                $type = $infoType === null ? 'none' : '0x' . dechex($infoType);

                throw new RuntimeException("Некорректный ответ A2S_INFO (type={$type})");
            }

            $protocol = ord($payload[0]);
            $payload = substr($payload, 1);

            $serverName = $this->readCString($payload);
            $currentMap = $this->readCString($payload);
            $this->readCString($payload); // «rust»
            $this->readCString($payload);

            $appid = unpack('v', substr($payload, 0, 2))[1];
            $payload = substr($payload, 2);

            $players = ord($payload[0]);
            $maxPlayers = ord($payload[1]);

            $playersList = [];

            $challengeResp = $this->sendAndRead(
                $socket,
                self::A2S_PLAYER_PREFIX . self::A2S_PLAYER_CHALLENGE_FAKE,
                4096
            );

            [$challengeType, $challengePayload] = $this->parseResponse($challengeResp);
            $playersPayload = null;

            if ($challengeType === 0x41 && strlen($challengePayload) >= 4) {
                $challenge = substr($challengePayload, 0, 4);
                $playersRaw = $this->sendAndRead($socket, self::A2S_PLAYER_PREFIX . $challenge, 65535);
                [$playersType, $playersPayload] = $this->parseResponse($playersRaw);

                if ($playersType !== 0x44) {
                    $playersPayload = null;
                }
            } elseif ($challengeType === 0x44) {
                $playersPayload = $challengePayload;
            }

            if ($playersPayload !== null && $playersPayload !== '') {
                $playersInResp = ord($playersPayload[0]);
                $playersPayload = substr($playersPayload, 1);

                for ($i = 0; $i < $playersInResp; $i++) {
                    if (strlen($playersPayload) < 9) {
                        break;
                    }
                    $playersPayload = substr($playersPayload, 1);
                    $name = $this->readCString($playersPayload);
                    $score = unpack('l', substr($playersPayload, 0, 4))[1];
                    $duration = unpack('f', substr($playersPayload, 4, 4))[1];
                    $playersPayload = substr($playersPayload, 8);

                    $playersList[] = [
                        'Name' => $name,
                        'Score' => $score,
                        'Time' => (int)$duration,
                    ];
                }
            }

            $status->online = true;
            $status->players = $players;
            $status->max_players = $maxPlayers;
            $status->map = $currentMap ?: 'unknown';
            $status->game = self::GAME_RUST;

            $status->setPlayersData($playersList);
            $status->touch();
        } catch (Throwable $e) {
            logs()->debug("Rust status update error for server {$server->ip}:{$server->port}: {$e->getMessage()}");

            $status->online = false;
            $status->touch();
        } finally {
            if ($socket && is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    private function readCString(string &$buffer): string
    {
        $pos = strpos($buffer, "\x00");
        if ($pos === false) {
            $str = $buffer;
            $buffer = '';

            return $str;
        }

        $str = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 1);

        return $str;
    }

    private function sendAndRead($socket, string $payload, int $length): string
    {
        fwrite($socket, $payload);

        return $this->readPacket($socket, $length);
    }

    private function readPacket($socket, int $length): string
    {
        $data = fread($socket, $length);

        if ($data === false || $data === '') {
            return '';
        }

        if (strlen($data) < 4) {
            return $data;
        }

        if (substr($data, 0, 4) !== "\xFF\xFF\xFF\xFE") {
            return $data;
        }

        return $this->readSplitPacket($socket, $data, $length);
    }

    private function readSplitPacket($socket, string $firstPacket, int $length): string
    {
        $packets = [];
        $isCompressed = false;
        $packetCount = null;
        $packetChecksum = null;
        $packet = $firstPacket;

        do {
            if (strlen($packet) < 12) {
                break;
            }

            $requestId = unpack('V', substr($packet, 4, 4))[1];
            $isCompressed = ($requestId & 0x80000000) !== 0;
            $packetCount = ord($packet[8]);
            $packetNumber = ord($packet[9]) + 1;

            if ($isCompressed) {
                if (strlen($packet) < 18) {
                    break;
                }

                $packetChecksum = unpack('V', substr($packet, 14, 4))[1];
                $payload = substr($packet, 18);
            } else {
                $payload = substr($packet, 12);
            }

            $packets[$packetNumber] = $payload;

            if ($packetCount !== null && count($packets) >= $packetCount) {
                break;
            }

            $packet = fread($socket, $length);
        } while ($packet !== false && $packet !== '' && substr($packet, 0, 4) === "\xFF\xFF\xFF\xFE");

        if ($packetCount === null || count($packets) !== $packetCount) {
            return $firstPacket;
        }

        ksort($packets);
        $data = implode('', $packets);

        if ($isCompressed) {
            if (!function_exists('bzdecompress')) {
                throw new RuntimeException('Получен сжатый пакет, но bz2 не установлен');
            }

            $data = bzdecompress($data);

            if (!is_string($data)) {
                throw new RuntimeException('Не удалось распаковать сжатый пакет');
            }

            if ($packetChecksum !== null && crc32($data) !== $packetChecksum) {
                throw new RuntimeException('CRC32 не совпадает');
            }
        }

        return $data;
    }

    private function parseResponse(string $raw): array
    {
        if ($raw === '' || strlen($raw) < 1) {
            return [null, ''];
        }

        if (substr($raw, 0, 4) === "\xFF\xFF\xFF\xFF") {
            if (strlen($raw) < 5) {
                return [null, ''];
            }

            return [ord($raw[4]), substr($raw, 5)];
        }

        return [ord($raw[0]), substr($raw, 1)];
    }
}
