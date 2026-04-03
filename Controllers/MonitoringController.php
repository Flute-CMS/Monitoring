<?php

namespace Flute\Modules\Monitoring\Controllers;

use Flute\Core\Router\Annotations\Route;
use Flute\Core\Support\BaseController;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Flute\Modules\Monitoring\Services\MonitoringPingService;
use Flute\Modules\Monitoring\Services\MonitoringService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for monitoring module
 */
class MonitoringController extends BaseController
{
    #[Route(name: 'monitoring.server.details', uri: 'api/monitoring/server/{id}', methods: ['GET'])]
    public function showServerDetails(Request $request, int $id)
    {
        $monitoringService = app(MonitoringService::class);
        $serverData = $monitoringService->getServerById($id);

        if (!$serverData) {
            return $this->error('Server not found', 404);
        }

        $server = $serverData['server'];
        $status = $serverData['status'];

        if (!$status) {
            $status = new ServerStatus();
            $status->server = $server;
        }

        $globalPing = MonitoringService::isPingEnabled();
        $queryPing = $request->query->get('show_ping');
        if ($queryPing === null || $queryPing === '') {
            $showPing = $globalPing;
        } else {
            $showPing = $globalPing && filter_var($queryPing, FILTER_VALIDATE_BOOLEAN);
        }

        return view('monitoring::pages.server-details', [
            'server' => $server,
            'status' => $status,
            'showPing' => $showPing,
        ]);
    }

    #[Route(name: 'monitoring.server.ping', uri: 'api/monitoring/ping/{id}', methods: ['GET'])]
    public function pingServer(Request $request, int $id)
    {
        if (!MonitoringService::isPingEnabled()) {
            return $this->json([
                'server_ping' => null,
                'ping_disabled' => true,
            ]);
        }

        $pingService = app(MonitoringPingService::class);

        return $this->json([
            'server_ping' => $pingService->getPing($id),
        ]);
    }

    /**
     * Batch endpoint: return cached pings for all enabled servers in one request.
     * If cache is empty (cron hasn't run yet), measure on the fly.
     */
    #[Route(name: 'monitoring.server.pings', uri: 'api/monitoring/pings', methods: ['GET'])]
    public function allPings(Request $request)
    {
        if (!MonitoringService::isPingEnabled()) {
            return $this->json([]);
        }

        $pingService = app(MonitoringPingService::class);
        $pings = $pingService->getAllPings();

        if (empty($pings)) {
            $lockKey = 'monitoring_pings_measuring';
            if (cache()->get($lockKey)) {
                return $this->json([]);
            }
            cache()->set($lockKey, true, 30);

            $cacheService = app(\Flute\Modules\Monitoring\Services\MonitoringCacheService::class);
            $servers = $cacheService->getEnabledServers();

            foreach ($servers as $server) {
                if (!$server->enabled) {
                    continue;
                }

                $ping = $pingService->measurePing($server);
                $pings[$server->id] = $ping ?? -1;
            }

            if (!empty($pings)) {
                cache()->set('monitoring_pings', $pings, 300);
            }
            cache()->delete($lockKey);
        }

        return $this->json($pings);
    }
}
