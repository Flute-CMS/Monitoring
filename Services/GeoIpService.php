<?php

namespace Flute\Modules\Monitoring\Services;

class GeoIpService
{
    private const CACHE_PREFIX = 'geoip_';

    private const CACHE_TTL = 86400; // 24 hours

    private const REQUEST_TIMEOUT = 1;

    /**
     * Resolve IP to geographic coordinates.
     * Results are cached for 24 hours per IP.
     * First call per unique IP may add ~1s latency; subsequent calls are instant.
     *
     * @return array{lat: float, lon: float}|null
     */
    public function getCoordinates(string $ip): ?array
    {
        if ($this->isLocal($ip)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . md5($ip);
        $cached = cache()->get($cacheKey);

        if ($cached !== null) {
            return $cached ?: null;
        }

        $coords = $this->resolve($ip);
        cache()->set($cacheKey, $coords ?: [], self::CACHE_TTL);

        return $coords;
    }

    private function resolve(string $ip): ?array
    {
        $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,lat,lon";

        $ctx = stream_context_create([
            'http' => [
                'timeout' => self::REQUEST_TIMEOUT,
                'connect_timeout' => self::REQUEST_TIMEOUT,
                'method' => 'GET',
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (!$data || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        return [
            'lat' => (float) $data['lat'],
            'lon' => (float) $data['lon'],
        ];
    }

    private function isLocal(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.');
    }
}
