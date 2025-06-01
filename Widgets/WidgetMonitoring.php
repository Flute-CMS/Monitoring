<?php

namespace Flute\Modules\Monitoring\Widgets;

use Flute\Core\Modules\Page\Widgets\AbstractWidget;
use Flute\Modules\Monitoring\Services\MonitoringService;

class WidgetMonitoring extends AbstractWidget
{
    private MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function getName() : string
    {
        return 'monitoring.widget';
    }

    public function getIcon() : string
    {
        return 'ph.regular.hard-drives';
    }

    public function render(array $settings) : string
    {
        $activeServers = $this->monitoringService->getActiveServers();
        $inactiveServers = $this->monitoringService->getInactiveServers();
        $totalPlayers = $this->monitoringService->getTotalPlayersCount();

        return view('monitoring::widgets.servers', [
            'activeServers' => $activeServers,
            'inactiveServers' => $inactiveServers,
            'totalPlayers' => $totalPlayers,
            'totalServers' => count($activeServers) + count($inactiveServers),
            'hideInactive' => filter_var($settings['hide_inactive'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'limit' => (int)($settings['limit'] ?? 5),
            'displayMode' => $settings['display_mode'] ?? 'standard',
            'showCountPlayers' => filter_var($settings['show_count_players'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'showPlaceholders' => filter_var($settings['show_placeholders'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ])->render();
    }

    public function getDefaultWidth() : int
    {
        return 12;
    }

    public function hasSettings() : bool
    {
        return true;
    }

    /**
     * Get default settings
     */
    public function getSettings() : array
    {
        return [
            'hide_inactive' => false,
            'limit' => 5,
            'display_mode' => 'standard',
            'show_placeholders' => true,
            'show_count_players' => true
        ];
    }

    /**
     * Returns the path to the settings form view.
     */
    public function renderSettingsForm(array $settings) : string
    {
        return view('monitoring::widgets.settings', [
            'settings' => $settings
        ])->render();
    }

    /**
     * Save settings
     */
    public function saveSettings(array $input) : array
    {
        return [
            'hide_inactive' => isset($input['hide_inactive']),
            'limit' => (int) ($input['limit'] ?? 5),
            'display_mode' => $input['display_mode'] ?? 'standard',
            'show_placeholders' => isset($input['show_placeholders']),
            'show_count_players' => isset($input['show_count_players'])
        ];
    }
}
