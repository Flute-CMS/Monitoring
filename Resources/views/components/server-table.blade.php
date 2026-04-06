@php
    $trans = 'monitoring.server';
    $showPing = ($showPing ?? true) && isset($userGeo);
@endphp

<div class="monitoring-table-rows">
    @foreach ($servers as $serverData)
        @php
            $server = $serverData['server'];
            $status = $serverData['status'];
            $isInactive = $serverData['isInactive'] ?? false;
            $hasError = isset($status->online) && !$status->online;
            $showActions = !$isInactive && !$hasError;
            $isCsgoLegacy = \Flute\Modules\Monitoring\Services\MonitoringService::isCsgoLegacy($status);

            $players = $status->players ?? 0;
            $maxPlayers = $status->max_players ?? 0;
            $ringCircum = 87.96;
            $fillPct = $maxPlayers > 0 ? ($players / $maxPlayers) * 100 : 0;
            $ringOffset = $maxPlayers > 0 ? $ringCircum * (1 - $players / $maxPlayers) : $ringCircum;
            $ringColor = 'var(--success)';
            if ($fillPct > 80) $ringColor = 'var(--error)';
            elseif ($fillPct > 50) $ringColor = 'var(--warning)';

            $statusClass = 'inactive';
            if (!$isInactive) {
                $statusClass = $hasError ? 'error' : 'online';
            }

            $mapPreview = $showActions ? $service->getMapPreview($status) : null;
        @endphp
        <div class="server-row {{ $statusClass }}"
            onclick="if(!event.target.closest('[data-copy],button,a'))openModal('server-details-{{ $server->id }}')">

            @if ($mapPreview)
                <div class="server-row-bg">
                    <img src="{{ $mapPreview }}" alt="{{ $status->map ?? '' }}" loading="lazy">
                </div>
            @endif
            <div class="server-row-overlay"></div>

            <div class="server-row-content">
                <div class="server-row-main">
                    <span class="server-row-dot {{ $statusClass }}"></span>

                    <div class="server-row-ring">
                        <svg viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="14" fill="none" stroke-width="2.5" class="server-ring-track" />
                            @if ($showActions && $players > 0)
                                <circle cx="18" cy="18" r="14" fill="none" stroke-width="2.5"
                                    stroke="{{ $ringColor }}" stroke-dasharray="{{ $ringCircum }}"
                                    stroke-dashoffset="{{ $ringOffset }}" stroke-linecap="round"
                                    class="server-ring-fill" />
                            @endif
                        </svg>
                        <span class="server-row-ring-text {{ !$showActions || $players === 0 ? 'dimmed' : '' }}">{{ $players }}</span>
                    </div>

                    <div class="server-row-info">
                        <h6 class="server-row-name">
                            {{ $server->name }}
                            @if ($isCsgoLegacy)
                                <span class="server-game-badge">CS:GO</span>
                            @endif
                        </h6>
                        <span class="server-row-ip"
                            data-tooltip="{{ __($trans . '.actions.copy_ip') }}"
                            data-copy="{{ $server->getConnectionString() }}">
                            {{ $server->getConnectionString() }}
                            · {{ $players }}/{{ $maxPlayers }}
                        </span>
                    </div>
                </div>

                <div class="server-row-map">
                    @if ($showActions)
                        <span class="server-map-pill">{{ $status->map ?? __($trans . '.player.unknown') }}</span>
                    @else
                        <span class="server-row-muted">—</span>
                    @endif
                </div>

                @if ($showPing && $server->getSetting('lat') && $server->getSetting('lon'))
                    <div class="server-row-ping">
                        @if ($showActions)
                            <span class="server-ping-badge" data-server-ping="{{ $server->id }}"
                                data-server-lat="{{ $server->getSetting('lat') }}" data-server-lon="{{ $server->getSetting('lon') }}"
                                data-tooltip="{{ __($trans . '.ping_measuring') }}">
                                <x-icon path="ph.bold.wifi-high-bold" />
                                <span class="server-ping-value">...</span>
                            </span>
                        @else
                            <span class="server-row-muted">—</span>
                        @endif
                    </div>
                @endif
            </div>

            @if ($showActions)
                <button class="server-play-btn"
                    onclick="navigator.clipboard?.writeText('connect {{ $server->getConnectionString() }}').catch(()=>{}); window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'"
                    data-tooltip="{{ __($trans . '.actions.play') }}">
                    <x-icon path="ph.regular.play" />
                </button>
            @endif
        </div>
    @endforeach
</div>

@foreach ($servers as $serverData)
    @include('monitoring::components.server-details-modal', [
        'server' => $serverData['server'],
        'showPing' => $showPing,
    ])
@endforeach
