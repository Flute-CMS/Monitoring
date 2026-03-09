<?php

namespace Flute\Modules\Monitoring\Widgets;

use Flute\Core\Modules\Page\Widgets\AbstractWidget;
use Flute\Modules\Monitoring\Services\MonitoringService;

class WidgetTotalOnline extends AbstractWidget
{
    private MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function getName(): string
    {
        return 'monitoring.total_online.widget';
    }

    public function getIcon(): string
    {
        return 'ph.regular.users-three';
    }

    public function render(array $settings): string
    {
        $totalPlayers = $this->monitoringService->getTotalPlayersCount();
        $allServers = $this->monitoringService->getAllServers();
        $activeServersCount = count(array_filter($allServers, static fn ($s) => $s['server']->enabled && ($s['status']->online === true)));

        return view('monitoring::widgets.total-online', [
            'totalPlayers' => $totalPlayers,
            'activeServersCount' => $activeServersCount,
            'totalServersCount' => count($allServers),
        ])->render();
    }

    public function getDefaultWidth(): int
    {
        return 6;
    }

    public function hasSettings(): bool
    {
        return false;
    }
}
