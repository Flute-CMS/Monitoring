<?php

namespace Flute\Modules\Monitoring\Services;

use Flute\Core\Database\Entities\Server;

/**
 * Measures and caches TCP/UDP/HTTP ping for game servers.
 *
 * Ping values are updated during the cron status update cycle and served
 * from cache to avoid per-request latency measurements.
 */
class MonitoringPingService
{
    private const CACHE_KEY = 'monitoring_pings';

    private const CACHE_TTL = 300;

    private const PING_TIMEOUT = 2;

    /** @var string[] Game mods that have no TCP listener — must use UDP probe */
    private const UDP_ONLY_MODS = ['samp', 'minecraft_bedrock'];

    /** @var string[] Game mods queried via HTTP */
    private const HTTP_MODS = ['gta5', 'fivem', 'redm'];

    /**
     * Get cached ping for a single server.
     * Returns milliseconds, null if offline, or null if not yet measured.
     */
    public function getPing(int $serverId): ?int
    {
        $all = $this->getAllPings();

        if (!isset($all[$serverId])) {
            return null;
        }

        $value = $all[$serverId];

        return $value === -1 ? null : $value;
    }

    /**
     * Get all cached pings as [serverId => ms | -1].
     *
     * @return array<int, int>
     */
    public function getAllPings(): array
    {
        return cache()->get(self::CACHE_KEY, []);
    }

    /**
     * Measure and cache pings for all given servers.
     * Called from MonitoringService::updateAllServersStatus().
     *
     * @param Server[] $servers
     */
    public function updatePings(array $servers): void
    {
        $pings = [];

        foreach ($servers as $server) {
            if (!$server->enabled) {
                continue;
            }

            $ping = $this->measurePing($server);
            $pings[$server->id] = $ping ?? -1;
        }

        cache()->set(self::CACHE_KEY, $pings, self::CACHE_TTL);
    }

    /**
     * Measure ping for a single server using the correct protocol.
     */
    public function measurePing(Server $server): ?int
    {
        $mod = $server->mod;

        // HTTP-based games (FiveM, RedM)
        if (in_array($mod, self::HTTP_MODS, true)) {
            return $this->pingHttp($server->ip, (int) $server->port);
        }

        // UDP-only games (SAMP, Minecraft Bedrock)
        if (in_array($mod, self::UDP_ONLY_MODS, true)) {
            $settings = $server->getSettings();
            $queryPort = !empty($settings['query_port']) ? (int) $settings['query_port'] : (int) $server->port;

            return $this->pingUdp($server->ip, $queryPort, $mod);
        }

        // Everything else: TCP connect time = pure network latency (SYN → SYN-ACK).
        // Works for Valve (CS2, CS:GO, Rust, TF2, CS 1.6), Minecraft Java, etc.
        $ping = $this->pingTcp($server->ip, (int) $server->port);

        if ($ping !== null) {
            return $ping;
        }

        // Fallback: try query port if configured and different from game port
        $settings = $server->getSettings();
        $queryPort = !empty($settings['query_port']) ? (int) $settings['query_port'] : null;

        if ($queryPort !== null && $queryPort !== (int) $server->port) {
            return $this->pingTcp($server->ip, $queryPort);
        }

        return null;
    }

    private function pingTcp(string $ip, int $port): ?int
    {
        $start = hrtime(true);
        $socket = @fsockopen($ip, $port, $errno, $errstr, self::PING_TIMEOUT);
        $end = hrtime(true);

        if ($socket) {
            fclose($socket);

            return (int) round(($end - $start) / 1_000_000);
        }

        return null;
    }

    private function pingUdp(string $ip, int $port, string $mod): ?int
    {
        $socket = @stream_socket_client("udp://{$ip}:{$port}", $errno, $errstr, self::PING_TIMEOUT);

        if (!$socket) {
            return null;
        }

        stream_set_timeout($socket, self::PING_TIMEOUT);

        try {
            $probe = $this->getUdpProbePacket($ip, $port, $mod);

            $start = hrtime(true);
            fwrite($socket, $probe);

            $read = [$socket];
            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, self::PING_TIMEOUT);
            $end = hrtime(true);

            if ($ready > 0) {
                $data = @fread($socket, 32);

                if ($data !== false && $data !== '') {
                    return (int) round(($end - $start) / 1_000_000);
                }
            }
        } finally {
            fclose($socket);
        }

        return null;
    }

    private function getUdpProbePacket(string $ip, int $port, string $mod): string
    {
        if ($mod === 'samp') {
            $header = 'SAMP';

            foreach (explode('.', $ip) as $octet) {
                $header .= chr((int) $octet);
            }

            $header .= chr($port & 0xFF);
            $header .= chr(($port >> 8) & 0xFF);

            return $header . 'i';
        }

        // Minecraft Bedrock (RakNet Unconnected Ping)
        $packet = chr(0x01);
        $packet .= pack('J', (int) (microtime(true) * 1000));
        $packet .= "\x00\xFF\xFF\x00\xFE\xFE\xFE\xFE\xFD\xFD\xFD\xFD\x12\x34\x56\x78";
        $packet .= pack('J', mt_rand());

        return $packet;
    }

    private function pingHttp(string $ip, int $port): ?int
    {
        if (!function_exists('curl_init')) {
            return $this->pingTcp($ip, $port);
        }

        $ch = curl_init("http://{$ip}:{$port}/dynamic.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::PING_TIMEOUT,
            CURLOPT_TIMEOUT => self::PING_TIMEOUT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $start = hrtime(true);
        curl_exec($ch);
        $end = hrtime(true);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode > 0 && $httpCode < 400) {
            return (int) round(($end - $start) / 1_000_000);
        }

        return null;
    }

}
