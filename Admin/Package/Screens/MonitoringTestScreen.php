<?php

namespace Flute\Modules\Monitoring\Admin\Package\Screens;

use Flute\Admin\Platform\Actions\Button;
use Flute\Admin\Platform\Fields\Input;
use Flute\Admin\Platform\Fields\Select;
use Flute\Admin\Platform\Layouts\LayoutFactory;
use Flute\Admin\Platform\Screen;
use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\Services\MonitoringService;
use Throwable;

class MonitoringTestScreen extends Screen
{
    public ?string $name = null;

    public ?string $description = null;

    public ?string $permission = 'admin.servers';

    protected ?Server $server = null;

    protected ?array $testResult = null;

    public function mount(): void
    {
        $this->name = __('monitoring.admin.test_title');
        $this->description = __('monitoring.admin.test_description');

        breadcrumb()->add(__('def.admin_panel'), url('/admin'))->add(
            __('monitoring.admin.title'),
            url('/admin/monitoring'),
        )->add(__('monitoring.admin.test_title'));

        $serverId = request()->request->get('id');
        if ($serverId) {
            $this->server = Server::findByPK((int) $serverId);
        }

        if (request()->has('test_submitted')) {
            $this->runTest();
        }
    }

    public function commandBar(): array
    {
        return [
            Button::make(__('monitoring.admin.back'))->icon('ph.regular.arrow-left')->href(url('/admin/monitoring')),
            Button::make(__('monitoring.admin.run_test'))->icon('ph.regular.play')->method('testConnection'),
        ];
    }

    public function layout(): array
    {
        $servers = Server::findAll(['enabled' => true]);
        $serverOptions = [];
        foreach ($servers as $server) {
            $serverOptions[$server->id] = $server->name . ' (' . $server->ip . ':' . $server->port . ')';
        }

        $layouts = [
            LayoutFactory::block([
                LayoutFactory::field(
                    Select::make('server_id')
                        ->options($serverOptions)
                        ->aligned()
                        ->value(request()->input('server_id', $this->server?->id ?? ''))
                        ->placeholder(__('monitoring.admin.select_server'))
                        ->empty(__('monitoring.admin.select_server')),
                )->label(__('monitoring.admin.server')),

                LayoutFactory::split([
                    LayoutFactory::field(
                        Input::make('custom_ip')
                            ->type('text')
                            ->value(request()->input('custom_ip', ''))
                            ->placeholder('127.0.0.1'),
                    )
                        ->label(__('monitoring.admin.custom_ip'))
                        ->popover(__('monitoring.admin.custom_ip_help')),

                    LayoutFactory::field(
                        Input::make('custom_port')
                            ->type('number')
                            ->value(request()->input('custom_port', ''))
                            ->placeholder('27015')
                            ->min(1)
                            ->max(65535),
                    )->label(__('monitoring.admin.custom_port')),
                ])->ratio('70/30'),

                LayoutFactory::field(
                    Select::make('protocol')
                        ->options([
                            'auto' => __('monitoring.admin.protocol_auto'),
                            'source' => 'Source Engine',
                            'goldsrc' => 'GoldSource (CS 1.6)',
                            'minecraft' => 'Minecraft',
                            'samp' => 'SA-MP',
                            'gta5' => 'GTA V (FiveM/RageMP)',
                            'rust' => 'Rust',
                        ])
                        ->aligned()
                        ->value(request()->input('protocol', 'auto')),
                )
                    ->label(__('monitoring.admin.protocol'))
                    ->popover(__('monitoring.admin.protocol_help')),

                LayoutFactory::field(
                    Input::make('timeout')
                        ->type('number')
                        ->value(request()->input('timeout', config('monitoring.connection_timeout', 3)))
                        ->min(1)
                        ->max(30),
                )
                    ->label(__('monitoring.admin.timeout'))
                    ->popover(__('monitoring.admin.timeout_help')),
            ])->title(__('monitoring.admin.test_settings')),
        ];

        if ($this->testResult !== null) {
            $layouts[] = LayoutFactory::block([
                LayoutFactory::view('monitoring::admin.test-result', [
                    'result' => $this->testResult,
                    'showPing' => MonitoringService::isPingEnabled(),
                ]),
            ])->title(__('monitoring.admin.test_result'));
        }

        return $layouts;
    }

    public function testConnection(): void
    {
        $this->runTest();
    }

    protected function runTest(): void
    {
        $serverId = request()->input('server_id');
        $customIp = trim(request()->input('custom_ip', ''));
        $customPort = (int) request()->input('custom_port', 0);
        $protocol = request()->input('protocol', 'auto');
        $timeout = max(1, min(30, (int) request()->input('timeout', 3)));

        $ip = '';
        $port = 27015;
        $mod = '730';

        if (!empty($customIp)) {
            $ip = $customIp;
            $port = $customPort > 0 ? $customPort : 27015;
        } elseif ($serverId) {
            $server = rep(Server::class)->findOne(['id' => (int) $serverId]);
            if ($server) {
                $ip = $server->ip;
                $port = $server->port;
                $mod = $server->mod;
            }
        }

        if (empty($ip)) {
            $this->testResult = [
                'success' => false,
                'error' => __('monitoring.admin.error_no_ip'),
                'data' => null,
                'time' => 0,
            ];

            return;
        }

        if ($protocol !== 'auto') {
            $protocolMap = [
                'source' => '730',
                'goldsrc' => '10',
                'minecraft' => 'minecraft',
                'samp' => 'samp',
                'gta5' => 'gta5',
                'rust' => 'rust',
            ];
            $mod = $protocolMap[$protocol] ?? '730';
        }

        $startTime = microtime(true);

        try {
            $monitoringService = app(MonitoringService::class);
            $status = $monitoringService->testServerConnection($ip, $port, $mod);

            $endTime = microtime(true);
            $queryTime = round(( $endTime - $startTime ) * 1000, 2);

            if ($status && $status->online) {
                $this->testResult = [
                    'success' => true,
                    'error' => null,
                    'data' => [
                        'ip' => $ip,
                        'port' => $port,
                        'online' => true,
                        'players' => $status->players,
                        'max_players' => $status->max_players,
                        'map' => $status->map,
                        'game' => $status->game,
                        'players_data' => $status->players_data,
                        'additional' => $status->additional,
                    ],
                    'time' => $queryTime,
                ];
            } else {
                $this->testResult = [
                    'success' => false,
                    'error' => __('monitoring.admin.error_offline'),
                    'data' => [
                        'ip' => $ip,
                        'port' => $port,
                        'online' => false,
                    ],
                    'time' => $queryTime,
                ];
            }
        } catch (Throwable $e) {
            $endTime = microtime(true);
            $queryTime = round(( $endTime - $startTime ) * 1000, 2);

            $this->testResult = [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'ip' => $ip,
                    'port' => $port,
                ],
                'time' => $queryTime,
            ];
        }
    }
}
