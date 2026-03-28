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

    public function getName(): string
    {
        return 'monitoring.widget';
    }

    public function getIcon(): string
    {
        return 'ph.regular.hard-drives';
    }

    public function render(array $settings): string
    {
        $allServers = $this->monitoringService->getAllServers();
        $activeServers = array_filter(
            $allServers,
            static fn($s) => $s['server']->enabled && $s['status']->online === true,
        );
        $inactiveServers = array_filter(
            $allServers,
            static fn($s) => $s['server']->enabled && $s['status']->online === false,
        );

        $totalPlayersOnline = 0;
        $totalMaxPlayers = 0;

        foreach ($activeServers as $serverData) {
            $totalPlayersOnline += $serverData['status']->players ?? 0;
            $totalMaxPlayers += $serverData['status']->max_players ?? 0;
        }

        return view('monitoring::widgets.servers', [
            'activeServers' => $activeServers,
            'inactiveServers' => $inactiveServers,
            'totalPlayers' => [
                'players' => $totalPlayersOnline,
                'max_players' => $totalMaxPlayers,
            ],
            'totalServers' => count($allServers),
            'hideInactive' => filter_var($settings['hide_inactive'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'limit' => (int) ( $settings['limit'] ?? 5 ),
            'displayMode' => $settings['display_mode'] ?? 'standard',
            'showCountPlayers' => filter_var($settings['show_count_players'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'showPlaceholders' => filter_var($settings['show_placeholders'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'showPing' => MonitoringService::isPingEnabled()
                && filter_var($settings['show_ping'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ])->render();
    }

    public function getDefaultWidth(): int
    {
        return 12;
    }

    public function hasSettings(): bool
    {
        return true;
    }

    /**
     * Get default settings
     */
    public function getSettings(): array
    {
        return [
            'hide_inactive' => false,
            'limit' => 50,
            'display_mode' => 'standard',
            'show_placeholders' => true,
            'show_count_players' => true,
            'show_ping' => true,
        ];
    }

    /**
     * Returns the path to the settings form view.
     */
    public function renderSettingsForm(array $settings): string
    {
        return view('monitoring::widgets.settings', [
            'settings' => $settings,
        ])->render();
    }

    /**
     * Save settings
     */
    public function saveSettings(array $input): array
    {
        return [
            'hide_inactive' => isset($input['hide_inactive']),
            'limit' => (int) ( $input['limit'] ?? 5 ),
            'display_mode' => $input['display_mode'] ?? 'standard',
            'show_placeholders' => isset($input['show_placeholders']),
            'show_count_players' => isset($input['show_count_players']),
            'show_ping' => isset($input['show_ping']),
        ];
    }
}
