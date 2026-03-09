<?php

namespace Flute\Modules\Monitoring\Listeners;

use Flute\Core\Events\ResponseEvent;

class HeadersListener
{
    public static function onRouteResponse(ResponseEvent $event): void
    {
        if (is_admin_path() || is_cli()) {
            return;
        }

        $response = $event->getResponse();

        $monitoringService = app('monitoring.service');

        $response->headers->set('Monitoring-count', $monitoringService->getTotalPlayersCount()['players']);
    }
}
