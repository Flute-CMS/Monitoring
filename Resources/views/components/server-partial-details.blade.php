@php
    $service = $app->get('monitoring.service');
    $hasError = isset($status->online) && !$status->online;
    $trans = 'monitoring.server';
    $showPing = filter_var(
        $showPing ?? \Flute\Modules\Monitoring\Services\MonitoringService::isPingEnabled(),
        FILTER_VALIDATE_BOOLEAN,
    );
    $serverId = $server->id;
    $mapPreview = $service->getMapPreview($status);
    $isCsgoLegacy = \Flute\Modules\Monitoring\Services\MonitoringService::isCsgoLegacy($status);

    $players = $status->players ?? 0;
    $maxPlayers = $status->max_players ?? 0;
    $ringCircum = 87.96;
    $ringOffset = $maxPlayers > 0 ? $ringCircum * (1 - $players / $maxPlayers) : $ringCircum;
    $fillPct = $maxPlayers > 0 ? ($players / $maxPlayers) * 100 : 0;
    $ringColor = 'var(--success)';
    if ($fillPct > 80) $ringColor = 'var(--error)';
    elseif ($fillPct > 50) $ringColor = 'var(--warning)';
@endphp

<div class="server-modal-split">
    <img src="{{ $mapPreview }}" alt="{{ $status->map ?? '' }}" class="server-modal-bg">

    <div class="server-modal-map">
        <div class="server-modal-map-fade"></div>
        <div class="server-modal-map-content">
            <h2 class="server-modal-title">{{ $server->name }}</h2>
            <p class="server-modal-subtitle">{{ $status->map ?? __($trans . '.player.unknown') }}</p>

            <div class="server-modal-pills">
                @if ($server->enabled && !$hasError)
                    @if ($isCsgoLegacy)
                        <span class="server-modal-pill server-modal-pill--csgo">CS:GO</span>
                    @endif
                    <span class="server-modal-pill">
                        <svg class="server-modal-ring" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="14" fill="none" stroke-width="2.5" stroke="var(--transp-2)" />
                            <circle cx="18" cy="18" r="14" fill="none" stroke-width="2.5"
                                stroke="{{ $ringColor }}" stroke-dasharray="{{ $ringCircum }}"
                                stroke-dashoffset="{{ $ringOffset }}" stroke-linecap="round"
                                style="transform:rotate(-90deg);transform-origin:center" />
                        </svg>
                        {{ $players }} / {{ $maxPlayers }}
                    </span>
                    @if ($showPing)
                        <span class="server-modal-pill server-modal-pill--ping" data-server-ping="{{ $server->id }}"
                            data-ping-ip="{{ $server->ip }}" data-ping-port="{{ $server->port }}"
                            data-tooltip="{{ __($trans . '.ping_measuring') }}">
                            <x-icon path="ph.bold.wifi-high-bold" />
                            <span class="server-ping-value">...</span>
                        </span>
                    @endif
                    <span class="server-modal-pill server-modal-pill--online">
                        <span class="server-modal-online-dot"></span>
                        {{ __($trans . '.online') }}
                    </span>
                @else
                    <span class="server-modal-pill server-modal-pill--offline">
                        {{ $server->enabled ? __($trans . '.error') : __($trans . '.inactive') }}
                    </span>
                @endif
            </div>

            <div class="server-modal-ip">
                <span class="copyable" data-copy="{{ $server->ip }}:{{ $server->port }}"
                    data-tooltip="{{ __($trans . '.actions.copy_ip') }}"
                    onclick="notyf.success('{{ __($trans . '.actions.copy_ip_success') }}')">
                    {{ $server->getConnectionString() }}
                </span>
                <x-icon path="ph.regular.copy" />
            </div>

            @if ($server->enabled && !$hasError)
                <x-button type="primary" class="server-modal-play-btn w-100"
                    onclick="navigator.clipboard?.writeText('connect {{ $server->ip }}:{{ $server->port }}').catch(()=>{}); window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
                    <x-icon path="ph.bold.play-fill" />
                    {{ __($trans . '.actions.play') }}
                </x-button>
            @endif
        </div>
    </div>

    <div class="server-modal-players">
        <div class="server-modal-players-header">
            <h3>
                {{ __($trans . '.player_list') }}
                @if ($players > 0)
                    <span class="server-modal-players-count">{{ $players }}</span>
                @endif
            </h3>
            @if ($players > 5)
                <div class="input__field-container server-modal-search">
                    <input type="text" class="server-details-players-search-input"
                        placeholder="{{ __($trans . '.player.search') }}">
                </div>
            @endif
        </div>

        @can('admin.servers')
            @if ($hasError && config('app.cron_mode'))
                <x-alert type="warning" class="server-details-message m-2" withClose="false">
                    {{ __($trans . '.cron_message') }}
                </x-alert>
            @endif

            @if ($server->mod == 730 && $players > 0)
                <x-alert type="warning" class="server-details-plugin-alert m-2" withClose="false">
                    {!! __($trans . '.plugin_alert', ['url' => 'https://github.com/Pisex/cs2-PlayersListCommand']) !!}
                </x-alert>
            @endif
        @endcan

        <div class="server-modal-players-list">
            @if ($server->enabled && !$hasError && $players > 0 && !empty($status->getPlayersData()))
                @php
                    $playersList = collect($status->getPlayersData());
                    $allEmpty = !$playersList->isEmpty() && $playersList->every(fn($p) => empty(trim($p['Name'])));
                @endphp

                @if ($allEmpty && user()->can('admin.servers'))
                    <x-alert type="warning" class="server-details-players-empty m-2" withClose="false">
                        {!! __($trans . '.empty_players_alert', ['url' => 'https://github.com/Source2ZE/ServerListPlayersFix']) !!}
                    </x-alert>
                @endif

                <table class="server-modal-table">
                    <thead>
                        <tr>
                            <th>{{ __($trans . '.player.name') }}</th>
                            <th class="text-center">{{ __($trans . '.player.score') }}</th>
                            <th class="text-center">{{ __($trans . '.player.time') }}</th>
                        </tr>
                    </thead>
                    <tbody id="playerTableBody-{{ $serverId }}">
                        @foreach ($playersList as $player)
                            <tr class="player-row">
                                <td class="player-name">{{ $player['Name'] ?: __($trans . '.player.unknown') }}</td>
                                <td class="text-center">{{ isset($player['Frags']) ? $player['Frags'] : ($player['Score'] ?? '0') }}</td>
                                <td class="text-center">{{ $player['Time'] ? gmdate('H:i:s', $player['Time']) : '00:00:00' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div id="noPlayersFound-{{ $serverId }}" class="server-details-empty" style="display: none;">
                    {{ __($trans . '.player.no_results') }}
                </div>
            @else
                <div class="server-details-empty">
                    @if (!$server->enabled)
                        {{ __($trans . '.maintenance') }}
                    @elseif ($hasError)
                        {{ __($trans . '.unavailable') }}
                    @else
                        {{ __($trans . '.no_players') }}
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal__footer modal__footer-server-details">
    <x-button type="outline-primary" class="w-100" data-a11y-dialog-hide="{{ 'server-details-' . $server->id }}">
        {{ __($trans . '.actions.close') }}
    </x-button>
    @if ($server->enabled && !$hasError)
        <x-button type="success" class="w-100"
            onclick="navigator.clipboard?.writeText('connect {{ $server->ip }}:{{ $server->port }}').catch(()=>{}); window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
            {{ __($trans . '.actions.play') }}
        </x-button>
    @endif
</div>
