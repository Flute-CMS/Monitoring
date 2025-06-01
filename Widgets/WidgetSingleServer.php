<?php

namespace Flute\Modules\Monitoring\Widgets;

use Flute\Core\Modules\Page\Widgets\AbstractWidget;
use Flute\Modules\Monitoring\Services\MonitoringService;

class WidgetSingleServer extends AbstractWidget
{
    private MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function getName() : string
    {
        return 'monitoring.single_server.widget';
    }

    public function getIcon() : string
    {
        return 'ph.regular.hard-drive';
    }

    public function render(array $settings) : string
    {
        $serverId = (int)($settings['server_id'] ?? 0);
        
        if (!$serverId) {
            return view('monitoring::widgets.single-server-empty')->render();
        }
        
        $serverData = $this->monitoringService->getServerById($serverId);
        
        if (!$serverData) {
            return view('monitoring::widgets.single-server-empty')->render();
        }
        
        return view('monitoring::widgets.single-server', [
            'serverData' => $serverData,
            'displayMode' => $settings['display_mode'] ?? 'standard',
            'hideModal' => filter_var($settings['hide_modal'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ])->render();
    }

    public function getDefaultWidth() : int
    {
        return 4;
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
            'server_id' => 0,
            'display_mode' => 'standard',
            'hide_modal' => false
        ];
    }

    /**
     * Returns the path to the settings form view.
     */
    public function renderSettingsForm(array $settings) : string
    {
        $servers = $this->monitoringService->getAllServers();
        
        return view('monitoring::widgets.single-server-settings', [
            'settings' => $settings,
            'servers' => $servers
        ])->render();
    }

    /**
     * Save settings
     */
    public function saveSettings(array $input) : array
    {
        return [
            'server_id' => (int)($input['server_id'] ?? 0),
            'display_mode' => $input['display_mode'] ?? 'standard',
            'hide_modal' => isset($input['hide_modal'])
        ];
    }
} 