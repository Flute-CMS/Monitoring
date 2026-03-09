<?php

namespace Flute\Modules\Monitoring\Admin\Package\Screens;

use Flute\Admin\Platform\Actions\Button;
use Flute\Admin\Platform\Actions\DropDown;
use Flute\Admin\Platform\Actions\ModalToggle;
use Flute\Admin\Platform\Fields\Input;
use Flute\Admin\Platform\Fields\Select;
use Flute\Admin\Platform\Fields\Toggle;
use Flute\Admin\Platform\Layouts\LayoutFactory;
use Flute\Admin\Platform\Screen;
use Flute\Admin\Platform\Support\Color;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Services\ConfigurationService;
use Flute\Modules\Monitoring\Services\MonitoringCacheService;
use Flute\Modules\Monitoring\Services\MonitoringService;

class MonitoringSettingsScreen extends Screen
{
    public ?string $name = null;
    public ?string $description = null;
    public ?string $permission = 'admin.servers';

    public function mount(): void
    {
        $this->name = __('monitoring.admin.title');
        $this->description = __('monitoring.admin.description');

        breadcrumb()
            ->add(__('def.admin_panel'), url('/admin'))
            ->add(__('monitoring.admin.title'));
    }

    public function commandBar(): array
    {
        return [
            DropDown::make(__('monitoring.admin.actions'))
                ->icon('ph.regular.gear')
                ->list([
                    Button::make(__('monitoring.admin.clear_cache'))
                        ->icon('ph.regular.trash')
                        ->method('clearCache')
                        ->type(Color::OUTLINE_DANGER)
                        ->confirm(__('monitoring.admin.clear_cache_confirm'))
                        ->fullWidth(),
                    Button::make(__('monitoring.admin.refresh_all'))
                        ->icon('ph.regular.arrows-clockwise')
                        ->method('refreshAllServers')
                        ->type(Color::OUTLINE_SUCCESS)
                        ->confirm(__('monitoring.admin.refresh_all_confirm'))
                        ->fullWidth(),
                ]),
            Button::make(__('monitoring.admin.test_server'))
                ->icon('ph.regular.play')
                ->href(url('/admin/monitoring/test')),
            Button::make(__('def.save'))
                ->icon('ph.regular.floppy-disk')
                ->method('save'),
        ];
    }

    public function layout(): array
    {
        $servers = Server::findAll(['enabled' => true]);

        return [
            LayoutFactory::columns([
                LayoutFactory::blank([
                    LayoutFactory::block([
                        LayoutFactory::field(
                            Input::make('connection_timeout')
                                ->type('number')
                                ->value(request()->input('connection_timeout', config('monitoring.connection_timeout', 3)))
                                ->min(1)
                                ->max(30)
                        )->label(__('monitoring.admin.connection_timeout'))
                            ->popover(__('monitoring.admin.connection_timeout_help')),

                        LayoutFactory::field(
                            Input::make('batch_size')
                                ->type('number')
                                ->value(request()->input('batch_size', config('monitoring.batch_size', 10)))
                                ->min(1)
                                ->max(50)
                        )->label(__('monitoring.admin.batch_size'))
                            ->popover(__('monitoring.admin.batch_size_help')),

                        LayoutFactory::field(
                            Input::make('cache_ttl')
                                ->type('number')
                                ->value(request()->input('cache_ttl', config('monitoring.cache_ttl', 60)))
                                ->min(30)
                                ->max(600)
                        )->label(__('monitoring.admin.cache_ttl'))
                            ->popover(__('monitoring.admin.cache_ttl_help')),

                        LayoutFactory::field(
                            Input::make('status_retention_days')
                                ->type('number')
                                ->value(request()->input('status_retention_days', config('monitoring.status_retention_days', 60)))
                                ->min(7)
                                ->max(365)
                        )->label(__('monitoring.admin.status_retention_days'))
                            ->popover(__('monitoring.admin.status_retention_days_help')),
                    ])->title(__('monitoring.admin.section_general')),

                    LayoutFactory::block([
                        LayoutFactory::field(
                            Toggle::make('show_navbar_online')
                                ->checked(filter_var(request()->input('show_navbar_online', config('monitoring.show_navbar_online', true)), FILTER_VALIDATE_BOOLEAN))
                        )->label(__('monitoring.admin.show_navbar_online'))
                            ->popover(__('monitoring.admin.show_navbar_online_help')),

                        LayoutFactory::field(
                            Toggle::make('show_offline_servers')
                                ->checked(filter_var(request()->input('show_offline_servers', config('monitoring.show_offline_servers', true)), FILTER_VALIDATE_BOOLEAN))
                        )->label(__('monitoring.admin.show_offline_servers'))
                            ->popover(__('monitoring.admin.show_offline_servers_help')),

                        LayoutFactory::field(
                            Toggle::make('show_player_details')
                                ->checked(filter_var(request()->input('show_player_details', config('monitoring.show_player_details', true)), FILTER_VALIDATE_BOOLEAN))
                        )->label(__('monitoring.admin.show_player_details'))
                            ->popover(__('monitoring.admin.show_player_details_help')),
                    ])->title(__('monitoring.admin.section_display')),
                ]),

                LayoutFactory::blank([
                    LayoutFactory::block([
                        LayoutFactory::field(
                            Select::make('default_display_mode')
                                ->options([
                                    'standard' => __('monitoring.settings.display_mode_standard'),
                                    'compact' => __('monitoring.settings.display_mode_compact'),
                                    'ultracompact' => __('monitoring.settings.display_mode_ultracompact'),
                                    'mode' => __('monitoring.settings.display_mode_mode'),
                                    'table' => __('monitoring.settings.display_mode_table'),
                                ])
                                ->value(request()->input('default_display_mode', config('monitoring.default_display_mode', 'standard')))
                        )->label(__('monitoring.admin.default_display_mode'))
                            ->popover(__('monitoring.admin.default_display_mode_help')),

                        LayoutFactory::field(
                            Input::make('default_servers_limit')
                                ->type('number')
                                ->value(request()->input('default_servers_limit', config('monitoring.default_servers_limit', 50)))
                                ->min(1)
                                ->max(200)
                        )->label(__('monitoring.admin.default_servers_limit'))
                            ->popover(__('monitoring.admin.default_servers_limit_help')),
                    ])->title(__('monitoring.admin.section_defaults')),

                    LayoutFactory::block([
                        LayoutFactory::field(
                            Toggle::make('faceit_enabled')
                                ->checked(filter_var(request()->input('faceit_enabled', config('monitoring.faceit.enabled', false)), FILTER_VALIDATE_BOOLEAN))
                        )->label(__('monitoring.admin.faceit_enabled'))
                            ->popover(__('monitoring.admin.faceit_enabled_help')),

                        LayoutFactory::field(
                            Input::make('faceit_api_key')
                                ->type('password')
                                ->value(request()->input('faceit_api_key', config('monitoring.faceit.api_key', '')))
                                ->placeholder('********')
                        )->label(__('monitoring.admin.faceit_api_key'))
                            ->popover(__('monitoring.admin.faceit_api_key_help')),
                    ])->title(__('monitoring.admin.section_integrations')),

                    LayoutFactory::block([
                        $this->renderServersStatus($servers),
                    ])->title(__('monitoring.admin.section_servers_status')),
                ]),
            ]),
        ];
    }

    protected function renderServersStatus(array $servers)
    {
        if (empty($servers)) {
            return LayoutFactory::view('monitoring::admin.no-servers');
        }

        $monitoringService = app(MonitoringService::class);
        $allServers = $monitoringService->getAllServers();

        $serverStatuses = [];
        foreach ($allServers as $data) {
            $serverStatuses[$data['server']->id] = $data['status'];
        }

        return LayoutFactory::view('monitoring::admin.servers-status', [
            'servers' => $servers,
            'serverStatuses' => $serverStatuses,
        ]);
    }

    public function save(): void
    {
        $data = request()->input();

        $config = [
            'connection_timeout' => max(1, min(30, (int) ($data['connection_timeout'] ?? 3))),
            'batch_size' => max(1, min(50, (int) ($data['batch_size'] ?? 10))),
            'cache_ttl' => max(30, min(600, (int) ($data['cache_ttl'] ?? 60))),
            'status_retention_days' => max(7, min(365, (int) ($data['status_retention_days'] ?? 60))),
            'show_navbar_online' => filter_var($data['show_navbar_online'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_offline_servers' => filter_var($data['show_offline_servers'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_player_details' => filter_var($data['show_player_details'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'default_display_mode' => in_array($data['default_display_mode'] ?? 'standard', ['standard', 'compact', 'ultracompact', 'mode', 'table'])
                ? $data['default_display_mode']
                : 'standard',
            'default_servers_limit' => max(1, min(200, (int) ($data['default_servers_limit'] ?? 50))),
            'faceit' => [
                'enabled' => filter_var($data['faceit_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'api_key' => trim($data['faceit_api_key'] ?? ''),
            ],
        ];

        $configPath = path('app/Modules/Monitoring/Resources/config/monitoring.php');
        $configDir = dirname($configPath);

        if (!is_dir($configDir)) {
            @mkdir($configDir, 0755, true);
        }

        $written = @file_put_contents(
            $configPath,
            '<?php return ' . var_export($config, true) . ";\n"
        );

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($configPath, true);
        }

        if ($written === false) {
            $this->flashMessage(__('def.server_error'), 'error');
            return;
        }

        config()->set('monitoring', $config);

        app(ConfigurationService::class)->loadCustomConfig($configPath, 'monitoring');

        $this->flashMessage(__('def.success'), 'success');
    }

    public function clearCache(): void
    {
        $cacheService = app(MonitoringCacheService::class);
        $cacheService->clearMonitoringCache();
        $cacheService->clearDashboardCache();

        $this->flashMessage(__('monitoring.admin.cache_cleared'), 'success');
    }

    public function refreshAllServers(): void
    {
        try {
            $monitoringService = app(MonitoringService::class);
            $monitoringService->updateAllServersStatus();

            $this->flashMessage(__('monitoring.admin.servers_refreshed'), 'success');
        } catch (\Throwable $e) {
            $this->flashMessage(__('monitoring.admin.refresh_error') . ': ' . $e->getMessage(), 'error');
        }
    }
}
