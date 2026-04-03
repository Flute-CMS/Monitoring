<?php

namespace Flute\Modules\Monitoring\Services;

class GeoIpService
{
    private const CACHE_PREFIX = 'geoip_';

    private const CACHE_TTL = 86400;

    private const REQUEST_TIMEOUT = 2;

    /**
     * @return array{lat: float, lon: float}|null
     */
    public function getCoordinates(string $ip): ?array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP) || $this->isLocal($ip)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . md5($ip);
        $cached = cache()->get($cacheKey);

        if ($cached !== null) {
            return $cached ?: null;
        }

        $coords = $this->resolveWithFallback($ip);
        cache()->set($cacheKey, $coords ?: [], self::CACHE_TTL);

        return $coords;
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function resolveWithFallback(string $ip): ?array
    {
        $providers = [
            fn() => $this->resolveIpApi($ip),
            fn() => $this->resolveIpWhois($ip),
            fn() => $this->resolveIpApiCo($ip),
        ];

        foreach ($providers as $provider) {
            try {
                $result = $provider();
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * ip-api.com — 45 req/min free, fastest, no key needed
     */
    private function resolveIpApi(string $ip): ?array
    {
        $response = $this->httpGet("http://ip-api.com/json/{$ip}?fields=status,lat,lon");

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        return $this->validateCoords((float) $data['lat'], (float) $data['lon']);
    }

    /**
     * ipwho.is — unlimited free, no key, fast
     */
    private function resolveIpWhois(string $ip): ?array
    {
        $response = $this->httpGet("https://ipwho.is/{$ip}?fields=latitude,longitude,success");

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['success'])) {
            return null;
        }

        return $this->validateCoords((float) $data['latitude'], (float) $data['longitude']);
    }

    /**
     * ipapi.co — 1000 req/day free, no key
     */
    private function resolveIpApiCo(string $ip): ?array
    {
        $response = $this->httpGet("https://ipapi.co/{$ip}/json/");

        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error'])) {
            return null;
        }

        if (!isset($data['latitude'], $data['longitude'])) {
            return null;
        }

        return $this->validateCoords((float) $data['latitude'], (float) $data['longitude']);
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function validateCoords(float $lat, float $lon): ?array
    {
        if ($lat === 0.0 && $lon === 0.0) {
            return null;
        }

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            return $this->curlGet($url);
        }

        return $this->streamGet($url);
    }

    private function curlGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: FluteCMS'],
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $code !== 200) {
            return null;
        }

        return $response;
    }

    private function streamGet(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => self::REQUEST_TIMEOUT,
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: FluteCMS\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);

        return $response !== false ? $response : null;
    }

    private function isLocal(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost', '0.0.0.0'], true)) {
            return true;
        }

        $filtered = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        return $filtered === false;
    }
}
