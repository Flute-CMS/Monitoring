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

        return view('monitoring::server-details', [
            'server' => $server,
            'status' => $status,
        ]);
    }

    #[Route(name: 'monitoring.server.ping', uri: 'api/monitoring/ping/{id}', methods: ['GET'])]
    public function pingServer(Request $request, int $id)
    {
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
        $pingService = app(MonitoringPingService::class);
        $pings = $pingService->getAllPings();

        if (empty($pings)) {
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
        }

        return $this->json($pings);
    }
}
