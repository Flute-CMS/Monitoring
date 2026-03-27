@php
    $service = $app->get('monitoring.service');
    $trans = 'monitoring.server';
    $displayMode = $displayMode ?? 'standard';
    $hasError = isset($status->online) && !$status->online;
    $isInactive = isset($isInactive) ? $isInactive : false;
    $isCsgoLegacy = \Flute\Modules\Monitoring\Services\MonitoringService::isCsgoLegacy($status);

    $players = $status->players ?? 0;
    $maxPlayers = $status->max_players ?? 0;
    $map = $status->map ?? __($trans . '.player.unknown');
    $fillPercentage = $maxPlayers > 0 ? ($players / $maxPlayers) * 100 : 0;
    $connect = $server->getConnectionString();
    $steamConnect = 'steam://connect/' . $server->ip . ':' . $server->port;
    $mapPreview = $service->getMapPreview($isInactive ? null : ($status ?? null));

    $ringCircum = 94.2;
    $ringOffset = $maxPlayers > 0 ? $ringCircum * (1 - $players / $maxPlayers) : $ringCircum;
    $ringColor = 'var(--success)';
    if ($fillPercentage > 80) {
        $ringColor = 'var(--error)';
    } elseif ($fillPercentage > 50) {
        $ringColor = 'var(--warning)';
    }
@endphp

@if ($displayMode === 'mode')
    <div class="monitoring-card-mode {{ $isInactive ? 'monitoring-card-inactive' : '' }}"
        @if (!isset($hideModal) || !$hideModal) onclick="openModal('server-details-{{ $server->id }}')" @endif>
        <div class="card-bg">
            <img src="{{ $mapPreview }}" alt="{{ $map }}" loading="lazy">
        </div>
        <div class="card-overlay"></div>
        <div class="card-content">
            <div class="card-name">{{ __($server->name) }}</div>
            <div class="card-meta">
                <span class="card-tag">{{ $map }}</span>
                @if ($isCsgoLegacy)
                    <span class="card-tag card-tag--csgo">CS:GO</span>
                @endif
            </div>
            <div class="card-bottom">
                @if (!$isInactive && !$hasError)
                    <svg class="card-ring" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15" fill="none" stroke-width="3" stroke="var(--transp-1)" />
                        <circle cx="18" cy="18" r="15" fill="none" stroke-width="3"
                            stroke="{{ $ringColor }}" stroke-dasharray="{{ $ringCircum }}"
                            stroke-dashoffset="{{ $ringOffset }}" stroke-linecap="round" />
                    </svg>
                    <span class="card-players">{{ $players }}<span> / {{ $maxPlayers }}</span></span>
                    <span class="card-ping" data-server-ping="{{ $server->id }}"
                        data-ping-ip="{{ $server->ip }}" data-ping-port="{{ $server->port }}"
                        data-tooltip="{{ __($trans . '.ping_measuring') }}">
                        <x-icon path="ph.bold.wifi-high-bold" />
                        <span class="server-ping-value">...</span>
                    </span>
                @else
                    <span class="card-players card-players--off">0<span> / 0</span></span>
                @endif
            </div>
        </div>
        @if (!$isInactive && !$hasError)
            <a href="{{ $steamConnect }}" class="card-play-btn" onclick="event.stopPropagation()"
                data-tooltip="{{ __($trans . '.actions.play') }}">
                <x-icon path="ph.regular.play" />
            </a>
        @endif
        <div class="card-hover-actions">
            <button class="card-copy-btn copyable" data-copy="connect {{ $connect }}" onclick="event.stopPropagation()"
                data-tooltip="{{ __($trans . '.actions.copy_ip') }}">
                <x-icon path="ph.regular.copy" />
            </button>
        </div>
    </div>
@else
    <article class="monitoring-card {{ $isInactive ? 'monitoring-card-inactive' : '' }} monitoring-card--{{ $displayMode }}"
        @if (!isset($hideModal) || !$hideModal) onclick="openModal('server-details-{{ $server->id }}')" @endif>
        <div class="card-bg">
            <img src="{{ $mapPreview }}" loading="lazy" alt="{{ $map }} map preview">
        </div>
        <div class="card-overlay"></div>
        <div class="card-content">
            <h3 class="card-name">{{ __($server->name) }}</h3>
            <div class="card-meta">
                <span class="card-tag">{{ $map }}</span>
                @if ($isCsgoLegacy)
                    <span class="card-tag card-tag--csgo">CS:GO</span>
                @endif
                @if (($isInactive || $hasError) && $displayMode !== 'ultracompact')
                    <span class="card-tag card-tag--off">{{ __($trans . '.offline') }}</span>
                @endif
            </div>

            <div class="card-bottom">
                @if (!$isInactive && !$hasError)
                    <svg class="card-ring" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15" fill="none" stroke-width="3" stroke="var(--transp-1)" />
                        <circle cx="18" cy="18" r="15" fill="none" stroke-width="3"
                            stroke="{{ $ringColor }}" stroke-dasharray="{{ $ringCircum }}"
                            stroke-dashoffset="{{ $ringOffset }}" stroke-linecap="round" />
                    </svg>
                    <span class="card-players">{{ $players }}<span> / {{ $maxPlayers }}</span></span>
                    <span class="card-ping" data-server-ping="{{ $server->id }}"
                        data-ping-ip="{{ $server->ip }}" data-ping-port="{{ $server->port }}"
                        data-tooltip="{{ __($trans . '.ping_measuring') }}">
                        <x-icon path="ph.bold.wifi-high-bold" />
                        <span class="server-ping-value">...</span>
                    </span>
                @else
                    <svg class="card-ring card-ring--off" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15" fill="none" stroke-width="3" stroke="var(--transp-1)" />
                    </svg>
                    <span class="card-players card-players--off">0<span> / 0</span></span>
                @endif
            </div>
        </div>

        @if (!$isInactive && !$hasError)
            <a href="{{ $steamConnect }}" class="card-play-btn" onclick="event.stopPropagation()"
                data-tooltip="{{ __($trans . '.actions.play') }}">
                <x-icon path="ph.regular.play" />
            </a>
        @endif
        <div class="card-hover-actions">
            <button class="card-copy-btn copyable" data-copy="connect {{ $connect }}" onclick="event.stopPropagation()"
                data-tooltip="{{ __($trans . '.actions.copy_ip') }}">
                <x-icon path="ph.regular.copy" />
            </button>
        </div>
    </article>
@endif

@if (!isset($hideModal) || !$hideModal)
    <x-modal id="server-details-{{ $server->id }}" title="{{ __($server->name) }}" class="server-details-modal"
        loadUrl="{{ url('api/monitoring/server/' . $server->id) }}">
        <x-slot name="skeleton">
            @include('monitoring::server-details-skeleton')
        </x-slot>
    </x-modal>
@endif
