<?php

namespace Flute\Modules\Monitoring\Controllers;

use Flute\Core\Router\Annotations\Route;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Support\BaseController;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for monitoring module
 */
class MonitoringController extends BaseController
{
    #[Route(name: 'monitoring.server.details', uri: 'api/monitoring/server/{id}', methods: ['GET'])]
    public function showServerDetails(Request $request, int $id)
    {
        $server = Server::findByPK($id);

        if (!$server) {
            return $this->error('Server not found', 404);
        }

        if (!$server->enabled) {
            return $this->error('Server not found', 404);
        }

        $status = ServerStatus::query()->where('server.id', $server->id)
            ->orderBy('updated_at', 'DESC')
            ->fetchOne();

        if (!$status) {
            $status = new ServerStatus();
            $status->server = $server;
        }

        return view('monitoring::server-details', [
            'server' => $server,
            'status' => $status
        ]);
    }
}
