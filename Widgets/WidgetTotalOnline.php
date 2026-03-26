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
        $allServers = $this->monitoringService->getAllServers();
        $activeServers = array_filter(
            $allServers,
            static fn($s) => $s['server']->enabled && $s['status']->online === true,
        );

        $totalPlayersOnline = 0;
        $totalMaxPlayers = 0;

        foreach ($activeServers as $serverData) {
            $totalPlayersOnline += $serverData['status']->players ?? 0;
            $totalMaxPlayers += $serverData['status']->max_players ?? 0;
        }

        return view('monitoring::widgets.total-online', [
            'totalPlayers' => [
                'players' => $totalPlayersOnline,
                'max_players' => $totalMaxPlayers,
            ],
            'activeServersCount' => count($activeServers),
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
