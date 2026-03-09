<?php

namespace Flute\Modules\Monitoring\Services\Protocols;

use Exception;

class CS16Protocol
{
    protected const A2S_INFO = "\xFF\xFF\xFF\xFF\x54\x53\x6F\x75\x72\x63\x65\x20\x45\x6E\x67\x69\x6E\x65\x20\x51\x75\x65\x72\x79\x00";

    protected const A2S_PLAYER = "\xFF\xFF\xFF\xFF\x55";

    protected const A2S_RULES = "\xFF\xFF\xFF\xFF\x56";

    protected const CHALLENGE_REQUEST = "\xFF\xFF\xFF\xFF\x57";

    protected const CONNECTION_TIMEOUT = 5;

    protected const SOCKET_TIMEOUT = 5;

    private $socket;

    private string $ip;

    private int $port;

    public function __construct(string $ip, int $port)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    public function connect(): bool
    {
        $errno = 0;
        $errstr = '';

        $this->socket = @fsockopen("udp://{$this->ip}", $this->port, $errno, $errstr, self::CONNECTION_TIMEOUT);

        if (!$this->socket) {
            logs()->debug("Failed to connect to CS 1.6 server {$this->ip}:{$this->port}: {$errstr} ({$errno})");

            return false;
        }

        stream_set_timeout($this->socket, self::SOCKET_TIMEOUT);

        return true;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function getInfo(): ?array
    {
        if (!$this->sendPacket(self::A2S_INFO)) {
            return null;
        }

        $response = $this->receivePacket();
        if (!$response) {
            return null;
        }

        return $this->parseInfoResponse($response);
    }

    public function getPlayers(): ?array
    {
        if (!$this->sendPacket(self::A2S_PLAYER . "\xFF\xFF\xFF\xFF")) {
            return null;
        }

        $response = $this->receiveResponse();

        if ($response === null) {
            return null;
        }

        if (ord($response[4]) === 0x41) {
            $challenge = substr($response, 5, 4);

            if (!$this->sendPacket(self::A2S_PLAYER . $challenge)) {
                return null;
            }

            $response = $this->receiveResponse();

            if ($response === null) {
                return null;
            }
        }

        return $this->parsePlayersResponseSafe($response);
    }

    protected function parsePlayersResponseSafe(string $data): ?array
    {
        if (strlen($data) < 6 || $data[4] !== "\x44") {
            return null;
        }

        $offset = 5;
        $playerCount = ord($data[$offset++]);
        $players = [];

        for ($i = 0; $i < $playerCount && $offset < strlen($data); $i++) {
            $index = ord($data[$offset++]);
            $name = $this->readString($data, $offset);

            if ($offset + 8 > strlen($data)) {
                break;
            }

            $score = (int) unpack('l', substr($data, $offset, 4))[1];
            $offset += 4;
            $time = (int) unpack('f', substr($data, $offset, 4))[1];
            $offset += 4;

            $players[] = [
                'index' => $index,
                'name' => $name,
                'score' => $score,
                'time' => $time,
            ];
        }

        return $players;
    }

    protected function sendPacket(string $data): bool
    {
        if (!$this->socket) {
            return false;
        }

        $result = fwrite($this->socket, $data);

        return $result !== false && $result === strlen($data);
    }

    protected function receivePacket(): ?string
    {
        if (!$this->socket) {
            return null;
        }

        $buffer = fread($this->socket, 4096);

        if ($buffer === false || $buffer === '') {
            return null;
        }

        return $buffer;
    }

    protected function parseInfoResponse(string $data): ?array
    {
        if (strlen($data) < 6) {
            return null;
        }

        $offset = 5;

        try {
            $info = [];

            $responseType = ord($data[4]);

            if ($responseType === 0x49) {
                $info['protocol'] = ord($data[$offset++]);

                $info['hostname'] = $this->readString($data, $offset);

                $info['map'] = $this->readString($data, $offset);

                // Game folder
                $info['game'] = $this->readString($data, $offset);

                // Game description
                $info['description'] = $this->readString($data, $offset);

                // App ID (2 bytes)
                if ($offset + 2 <= strlen($data)) {
                    $info['app_id'] = unpack('v', substr($data, $offset, 2))[1];
                    $offset += 2;
                }

                // Players, Max players, Bots
                if ($offset + 3 <= strlen($data)) {
                    $info['players'] = ord($data[$offset++]);
                    $info['max_players'] = ord($data[$offset++]);
                    $info['bots'] = ord($data[$offset++]);
                }

                // Server type, Environment, Visibility, VAC
                if ($offset + 4 <= strlen($data)) {
                    $info['type'] = chr(ord($data[$offset++]));
                    $info['environment'] = chr(ord($data[$offset++]));
                    $info['visibility'] = ord($data[$offset++]);
                    $info['vac'] = ord($data[$offset++]);
                }

                // Version
                if ($offset < strlen($data)) {
                    $info['version'] = $this->readString($data, $offset);
                }
            } elseif ($responseType === 0x6D) {
                // Old Goldsource format
                // Address (IP:Port)
                $info['address'] = $this->readString($data, $offset);

                // Server name
                $info['hostname'] = $this->readString($data, $offset);

                // Map
                $info['map'] = $this->readString($data, $offset);

                // Game directory
                $info['game'] = $this->readString($data, $offset);

                // Game description
                $info['description'] = $this->readString($data, $offset);

                if ($offset + 7 <= strlen($data)) {
                    // Players count
                    $info['players'] = ord($data[$offset++]);

                    // Max players
                    $info['max_players'] = ord($data[$offset++]);

                    // Protocol version
                    $info['protocol'] = ord($data[$offset++]);

                    // Server type
                    $info['type'] = chr(ord($data[$offset++]));

                    // Environment
                    $info['environment'] = chr(ord($data[$offset++]));

                    // Visibility
                    $info['visibility'] = ord($data[$offset++]);

                    // Mod info
                    $info['mod'] = ord($data[$offset++]);

                    if ($info['mod'] === 1 && $offset + 12 <= strlen($data)) {
                        // Mod URL
                        $info['mod_url'] = $this->readString($data, $offset);

                        // Mod download URL
                        $info['mod_download'] = $this->readString($data, $offset);

                        // Skip null byte
                        $offset++;

                        // Mod version
                        $info['mod_version'] = unpack('V', substr($data, $offset, 4))[1];
                        $offset += 4;

                        // Mod size
                        $info['mod_size'] = unpack('V', substr($data, $offset, 4))[1];
                        $offset += 4;

                        // Mod type
                        $info['mod_type'] = ord($data[$offset++]);

                        // Mod DLL
                        $info['mod_dll'] = ord($data[$offset++]);
                    }

                    // VAC
                    if ($offset < strlen($data)) {
                        $info['vac'] = ord($data[$offset++]);
                    }

                    // Bot count
                    if ($offset < strlen($data)) {
                        $info['bots'] = ord($data[$offset++]);
                    }
                }
            } else {
                return null;
            }

            return $info;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function parsePlayersResponse(string $data): ?array
    {
        if (strlen($data) < 6) {
            return null;
        }

        $offset = 5;

        try {
            $playerCount = ord($data[$offset++]);
            $players = [];

            for ($i = 0; $i < $playerCount && $offset < strlen($data); $i++) {
                $player = [];

                $player['index'] = ord($data[$offset++]);

                $player['name'] = $this->readString($data, $offset);

                if ($offset + 8 <= strlen($data)) {
                    $player['score'] = unpack('l', substr($data, $offset, 4))[1];
                    $offset += 4;

                    $player['time'] = unpack('f', substr($data, $offset, 4))[1];
                    $offset += 4;

                    $players[] = $player;
                }
            }

            return $players;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function readString(string $data, int &$offset): string
    {
        $nullPos = strpos($data, "\0", $offset);
        if ($nullPos === false) {
            $result = substr($data, $offset);
            $offset = strlen($data);

            return $result;
        }

        $result = substr($data, $offset, $nullPos - $offset);
        $offset = $nullPos + 1;

        return $result;
    }

    protected function receiveResponse(): ?string
    {
        if (!$this->socket) {
            return null;
        }

        $chunks = [];
        $needChunks = 1;
        $id = null;
        $compressed = false;
        $deadline = microtime(true) + self::SOCKET_TIMEOUT;

        while (microtime(true) < $deadline) {
            $buf = @fread($this->socket, 4096);
            if ($buf === '' || $buf === false) {
                continue;
            }

            /* одиночный пакет */
            if (substr($buf, 0, 4) === "\xFF\xFF\xFF\xFF") {
                return $buf;
            }

            /* split-пакет */
            if (substr($buf, 0, 4) !== "\xFE\xFF\xFF\xFF" || strlen($buf) < 9) {
                continue;                       // мусор – пропускаем
            }

            $curId = substr($buf, 4, 4);
            if ($id === null) {
                $id = $curId;
            } elseif ($curId !== $id) {
                continue;                       // фрагмент другого ответа
            }

            /* пытаемся определить, GoldSrc это или Source */
            $byte8 = ord($buf[8]);
            $byte9 = ord($buf[9] ?? "\x00");

            if ($byte8 >> 4 || ($byte8 & 0x0F)) {              // похоже на GoldSrc
                $fragNo = $byte8 >> 4;
                $fragTotal = $byte8 & 0x0F;
                $payload = substr($buf, 9);
            } else {                                           // Source
                if (strlen($buf) < 11) {
                    continue;
                }
                $fragTotal = $byte8;
                $fragNo = $byte9;
                $payload = substr($buf, 11);                 // пропускаем size (2 байта) только если он есть
            }

            $chunks[$fragNo] = $payload;
            $needChunks = $fragTotal;

            if (count($chunks) === $needChunks) {
                ksort($chunks);
                $data = implode('', $chunks);

                /* Source может прислать bzip2 – проверяем старший бит ID */
                if ((ord($curId[3]) & 0x80) !== 0) {           // 0x80000000
                    $compressed = true;
                }
                if ($compressed) {
                    $data = bzdecompress($data);
                    if ($data === false) {
                        return null;
                    }
                }

                return "\xFF\xFF\xFF\xFF" . $data;              // нормализуем заголовок
            }
        }

        return null;   // не успели
    }
}
