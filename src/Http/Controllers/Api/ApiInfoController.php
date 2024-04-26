<?php

namespace Flute\Modules\Source_Monitoring\src\Http\Controllers\Api;

use Flute\Core\Support\AbstractController;
use Flute\Core\Support\FluteRequest;
use Flute\Modules\Source_Monitoring\src\Services\ServersMonitorService;

class ApiInfoController extends AbstractController
{
    public function getDetailInfo(FluteRequest $request, ServersMonitorService $monitorService) {

        $serverId = $request->input('server_id', '0');
        $force = $request->input('force', false);

        $server = $monitorService->findServer($serverId);

        if (empty($server) || $server === null) {
            return $this->error(__('monitoring.info.server_not_found'), 404);
        }

        $result = $monitorService->monitorInfo($server[0], $force);

        return $this->json($result);
    }
}