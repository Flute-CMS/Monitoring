<?php

namespace Flute\Modules\Source_Monitoring\src\Services;

use Flute\Core\Database\Entities\Server;
use xPaw\SourceQuery\SourceQuery;
use GuzzleHttp\Client;

class ServersMonitorService
{
    protected const CACHE_KEY = 'flute.monitoring.servers.';
    protected const CACHE_DEFAULT_TIME = 300;
    protected const CACHE_PERFORMANCE_TIME = 600;
    protected const MONITORING_INFO_URL = 'http://ip:port/monitoring-info';

    public function monitor(array $servers): array
    {
        $queries = [];
        foreach ($servers as $server) {
            $queries[] = $this->getInfo($server);
        }
        return $queries;
    }

    public function getInfo(Server $server, bool $wasTried = false): array
    {
        $cacheKey = self::CACHE_KEY . $server->id;

        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        if (!$server->enabled) {
            return $this->noConnectServer($server);
        }

        $serverResult = $this->fetchServerQuery($server, $wasTried);
        $serverResult['id'] = $server->id;

        cache()->set($cacheKey, $serverResult, is_performance() ? self::CACHE_PERFORMANCE_TIME : self::CACHE_DEFAULT_TIME);

        return $serverResult;
    }

    protected function fetchMonitoringInfo(Server $server): ?array
    {
        $client = new Client(['timeout' => 6, 'connect_timeout' => 6]);
        $url = str_replace(['ip', 'port'], [$server->ip, $server->port], self::MONITORING_INFO_URL);

        try {
            $response = $client->get($url);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);

                if ($data) {
                    return [
                        'ip' => $server->ip,
                        'port' => $server->port,
                        'info' => $data,
                        'serverName' => $server->name,
                        'displayIp' => $server->display_ip,
                        'status' => 'online',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log the exception if needed
        }

        return null;
    }

    protected function fetchServerQuery(Server $server, bool $wasTried): array
    {
        $query = null;
        $serverResult = [];

        try {
            $query = new SourceQuery();
            $query->Connect($server->ip, $server->port, 2, ((int) $server->mod === 10) ? SourceQuery::GOLDSOURCE : SourceQuery::SOURCE);
            $serverResult = $this->processServerQuery($server, $query);
        } catch (\Exception $e) {
            if ($wasTried) {
                $serverResult = $this->noConnectServer($server);
            } else {
                return $this->getInfo($server, true);
            }
        } finally {
            if ($query !== null) {
                $query->Disconnect();
            }
        }

        return $serverResult;
    }

    protected function noConnectServer(Server $server): array
    {
        return [
            'id' => $server->id,
            'ip' => $server->ip,
            'port' => $server->port,
            'serverName' => $server->name,
            'serverMod' => $server->mod,
            'serverHostName' => 'Server is Offline',
            'displayIp' => $server->display_ip,
            'status' => 'offline',
            'info' => [
                'percentOnline' => $this->getPercentName(0, 1),
                'Players' => '-',
                'MaxPlayers' => '-',
                'Map' => 'monitoring.no_map',
                'Map_img' => $this->getMapImg($server->mod, '', '-'),
                'Map_pin' => $this->getMapPin('_'),
                'HostName' => 'monitoring.info.server_is_shutdown',
                'HostName_replace' => $server->name,
            ],
        ];
    }

    protected function processServerQuery(Server $server, SourceQuery $query): array
    {
        $info = $query->GetInfo();
        $players = $query->GetPlayers();

        $serverResult = [
            'ip' => $server->ip,
            'port' => $server->port,
            'info' => $info,
            'players' => $players,
            'serverName' => $server->name,
            'serverMod' => $server->mod,
            'serverHostName' => $info['HostName'],
            'displayIp' => $server->display_ip,
            'status' => 'online',
        ];

        $serverResult['info']['HostName_replace'] = $server->name;
        $serverResult['info']['percentOnline'] = $this->getPercentName($info['Players'], $info['MaxPlayers']);

        if ($info['ModDir'] == "cstrike") { $info['ModDir'] = "cs"; }
        if (isset($info['Map'])) {
            $serverResult['info']['Map_img'] = $this->getMapImg($server->mod, $info['ModDir'], strtolower($info['Map']));
            $serverResult['info']['Map_pin'] = $this->getMapPin($info['Map']);
        }

        return $serverResult;
    }

    protected function getPercentName(int $players, int $maxPlayers): array
    {
        $percent = ((int) $players / $maxPlayers) * 100;

        $name = 'green';
        if ($percent > 80) {
            $name = 'error';
        } elseif ($percent > 40) {
            $name = 'warning';
        }

        return [
            'name' => $name,
            'percent' => $percent,
        ];
    }

    protected function getMapPin(string $map): string
    {
        $mapPath = sprintf("%s/public/assets/img/pins/_%s.webp", BASE_PATH, $map);
        if (file_exists($mapPath)) {
            return str_replace(BASE_PATH . '/public/', '', $mapPath);
        }

        return 'assets/img/pins/_.webp';
    }

    protected function getMapImg(string $mod, string $moddir, string $map): string
    {
        $mapPath = sprintf("%s/public/assets/img/maps/%s/%s.webp", BASE_PATH, $mod, $map);
        if (file_exists($mapPath)) {
            $mapPath = str_replace(BASE_PATH . '/public/', '', $mapPath);
        } else {
            $mapPath = sprintf('https://image.gametracker.com/images/maps/160x120/%s/%s.jpg', $moddir, $map);
        }
		return $mapPath;
    }

    public function findServer(int $serverId): array
    {
        return rep(Server::class)->select()->where('id', '=', $serverId)->where('enabled', true)->fetchAll();
    }
}
