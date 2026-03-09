<?php

namespace Flute\Modules\Monitoring\Controllers;

use Flute\Core\Router\Annotations\Route;
use Flute\Core\Support\BaseController;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
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
}
