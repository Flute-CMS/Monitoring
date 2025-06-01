<?php

namespace Flute\Modules\Monitoring\Services;

use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Flute\Modules\Monitoring\Services\MonitoringCacheService;

class MapImageService
{
    private MonitoringCacheService $cacheService;

    public function __construct(MonitoringCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function getMapPreview(?ServerStatus $status): string
    {
        if (!$status) {
            return asset('assets/img/maps/730/-.webp');
        }

        $mapPath = $this->getMapImagePath($status);
        $localMapPath = path('public/' . $mapPath);

        if (file_exists($localMapPath)) {
            return asset($mapPath);
        }

        $mapDir = dirname($localMapPath);
        if (!is_dir($mapDir)) {
            mkdir($mapDir, 0755, true);
        }

        // $modDir = $this->getModDirForGametracker($status->server->mod, $status->game);
        // if ($this->shouldFetchFromGametracker($status, $modDir)) {
        //     $imageUrl = $this->tryFetchMapImageFromGametracker($status, $modDir, $mapPath);
        //     if ($imageUrl) {
        //         return $imageUrl;
        //     }
        // }

        return $this->getDefaultMapImage($status->server->mod);
    }

    protected function getMapImagePath(ServerStatus $status): string
    {
        return 'assets/img/maps/' . $status->server->mod . '/' . $status->map . '.webp';
    }

    protected function shouldFetchFromGametracker(ServerStatus $status, ?string $modDir): bool
    {
        return $status->map
            && $status->map !== '-'
            && $modDir !== null
            && $modDir !== MonitoringService::GAME_MINECRAFT;
    }

    protected function tryFetchMapImageFromGametracker(ServerStatus $status, string $modDir, string $mapPath): ?string
    {
        $gametrackerUrl = sprintf(
            'https://image.gametracker.com/images/maps/160x120/%s/%s.jpg',
            $modDir,
            $status->map
        );

        $cacheKey = 'gametracker_map_' . $modDir . '_' . $status->map;

        $cachedImageUrl = $this->cacheService->getRaw($cacheKey);
        if ($cachedImageUrl) {
            return $cachedImageUrl;
        }

        try {
            $imageContent = $this->downloadImage($gametrackerUrl);
            if ($imageContent) {
                file_put_contents(path('public/' . $mapPath), $imageContent);
                $assetPath = asset($mapPath);
                $this->cacheService->setRaw($cacheKey, $assetPath, 999999); // Long TTL for map images
                return $assetPath;
            }
        } catch (\Exception $e) {
            logs()->warning("Failed to download map image from gametracker: {$e->getMessage()}");
        }

        return null;
    }

    protected function downloadImage(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, MonitoringService::CONNECTION_TIMEOUT); // Assuming CONNECTION_TIMEOUT is accessible or redefine
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, 'https://www.gametracker.com/');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $imageContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($imageContent && $httpCode === 200) ? $imageContent : null;
    }

    protected function getDefaultMapImage(string $mod): string
    {
        $defaultModPath = path('public/assets/img/maps/' . $mod . '/-.webp');
        if (file_exists($defaultModPath)) {
            return asset('assets/img/maps/' . $mod . '/-.webp');
        }

        return asset('assets/img/maps/730/-.webp');
    }

    protected function getModDirForGametracker(string $mod, ?string $game): ?string
    {
        $modMap = [
            MonitoringService::GAME_CSGO => 'csgo',
            MonitoringService::GAME_CSS => 'css',
            MonitoringService::GAME_CS16 => 'cs',
            MonitoringService::GAME_TF2 => 'tf2',
            MonitoringService::GAME_L4D2 => 'l4d2',
            MonitoringService::GAME_GARRYSMOD => 'garrysmod',
            '251570' => '7daystodie',
            '252490' => 'rust',
            '346110' => 'arkse',
            MonitoringService::GAME_MINECRAFT => MonitoringService::GAME_MINECRAFT,
            MonitoringService::GAME_GTA5 => MonitoringService::GAME_GTA5,
            MonitoringService::GAME_SAMP => MonitoringService::GAME_SAMP,
        ];

        return $modMap[$mod] ?? ($modMap[$game] ?? null);
    }
}
