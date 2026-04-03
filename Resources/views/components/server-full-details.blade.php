@php
    $server = $server ?? null;
    $status = $status ?? null;
    $serverId = $server ? $server->id : 0;
    $additionalData = ($status && !empty($status->additional)) ? json_decode($status->additional, true) : null;
    $isCsGo = $status && $status->game === '730' && !empty($additionalData['players']);
    $isCsgoLegacy = \Flute\Modules\Monitoring\Services\MonitoringService::isCsgoLegacy($status);
    $service = $app->get('monitoring.service');
    $hasError = isset($status->online) && !$status->online;
    $trans = 'monitoring.server';
    $serverLat = $server ? $server->getSetting('lat') : null;
    $serverLon = $server ? $server->getSetting('lon') : null;
    $showPing = filter_var(
        $showPing ?? \Flute\Modules\Monitoring\Services\MonitoringService::isPingEnabled(),
        FILTER_VALIDATE_BOOLEAN,
    ) && $serverLat && $serverLon;
    $serverStats = $service->getServerStats($server);
    $mapPreview = $service->getMapPreview($status);

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
                        {{ __($trans . '.offline') }}
                    </span>
                @endif
            </div>

            <div class="server-modal-ip"
                data-copy="connect {{ $server->getConnectionString() }}"
                data-tooltip="{{ __($trans . '.actions.copy_ip') }}">
                <span>{{ $server->getConnectionString() }}</span>
                <x-icon path="ph.regular.copy" />
            </div>

            @if ($server->enabled && !$hasError)
                <x-button type="primary" class="server-modal-play-btn w-100"
                    onclick="navigator.clipboard?.writeText('connect {{ $server->getConnectionString() }}').catch(()=>{}); window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
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
            <div class="server-modal-players-actions">
                @if ($players > 5)
                    <div class="input__field-container server-modal-search">
                        <input type="text" class="server-details-players-search-input"
                            placeholder="{{ __($trans . '.player.search') }}">
                    </div>
                @endif
            </div>
        </div>

        <div class="server-modal-players-list">
            @if ($server->enabled && !$hasError && $players > 0 && !empty($additionalData['players']))
                <table class="server-modal-table">
                    <thead>
                        <tr>
                            <th>{{ __($trans . '.player.name') }}</th>
                            <th class="text-center" data-tooltip="{{ __($trans . '.csgo.kills') }}">
                                <x-icon path="ph.regular.sword" />
                            </th>
                            <th class="text-center" data-tooltip="{{ __($trans . '.csgo.deaths') }}">
                                <x-icon path="ph.regular.skull" />
                            </th>
                            <th class="text-center" data-tooltip="{{ __($trans . '.csgo.playtime') }}">
                                <x-icon path="ph.regular.hourglass" />
                            </th>
                            @if ($showPing)
                                <th class="text-center" data-tooltip="{{ __($trans . '.csgo.ping') }}">
                                    <x-icon path="ph.bold.wifi-high-bold" />
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="playerTableBody-{{ $serverId }}">
                        @foreach ($additionalData['players'] as $player)
                            @php
                                $playerSteamInfo = $player['steam_info'] ?? [];
                                $isT = $player['team'] == 2;
                                $faceitInfo = $player['faceit_info'] ?? null;
                                $fallbackAvatar = asset(config('profile.default_avatar'));
                                $pingVal = max(1, (int) ($player['ping'] ?? 0));
                                $pingClass = $pingVal < 60 ? 'good' : ($pingVal < 120 ? 'medium' : 'bad');
                            @endphp
                            <tr class="player-row {{ $isT ? 't-row' : 'ct-row' }}" data-team="{{ $isT ? 't' : 'ct' }}">
                                <td class="player-info">
                                    <a href="{{ url('profile/search/' . $player['steamid']) }}" hx-boost="true"
                                        hx-target="#main" data-user-card="" hx-swap="outerHTML"
                                        onclick="closeModal('server-details-{{ $server->id }}')">
                                        @if ($faceitInfo && $faceitInfo['rank_image'])
                                            <div class="player-faceit-rank">
                                                <img src="{{ $faceitInfo['rank_image'] }}"
                                                    alt="Faceit Level {{ $faceitInfo['skill_level'] }}" loading="lazy"
                                                    data-tooltip="Faceit Level {{ $faceitInfo['skill_level'] }} ({{ $faceitInfo['faceit_elo'] }} ELO)">
                                            </div>
                                        @endif
                                        <div class="player-avatar">
                                            @if (!empty($playerSteamInfo['avatar']))
                                                <img src="{{ $playerSteamInfo['avatar'] }}" alt="{{ $playerSteamInfo['name'] ?? ($player['name'] ?? 'Player') }}">
                                            @elseif(!empty($player['avatar']))
                                                <img src="{{ $player['avatar'] }}" alt="{{ $player['name'] }}">
                                            @else
                                                <img src="{{ $fallbackAvatar }}" alt="{{ $player['name'] ?? 'Player' }}">
                                            @endif
                                        </div>
                                        <div class="player-name">
                                            {{ $playerSteamInfo['name'] ?? $player['name'] }}
                                        </div>
                                    </a>
                                </td>
                                <td class="text-center">{{ $player['kills'] ?? 0 }}</td>
                                <td class="text-center">{{ $player['death'] ?? 0 }}</td>
                                <td class="text-center">
                                    {{ \Carbon\CarbonInterval::seconds($player['playtime'])->cascade()->forHumans(null, true) }}
                                </td>
                                @if ($showPing)
                                    <td class="text-center player-ping">
                                        <span class="ping-value {{ $pingClass }}"
                                            data-tooltip="{{ __($trans . '.csgo.ping') }} {{ $pingVal }} ms">
                                            {{ $pingVal }}
                                        </span>
                                    </td>
                                @endif
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
            onclick="navigator.clipboard?.writeText('connect {{ $server->getConnectionString() }}').catch(()=>{}); window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
            {{ __($trans . '.actions.play') }}
        </x-button>
    @endif
</div>
