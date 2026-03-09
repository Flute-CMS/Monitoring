@php
    $service = $app->get('monitoring.service');
    $trans = 'monitoring.server';
    $displayMode = $displayMode ?? 'standard';
    $hasError = isset($status->online) && !$status->online;
    $isInactive = isset($isInactive) ? $isInactive : false;
    $percentColor = 'success';

    if (!$isInactive && isset($status->max_players)) {
        $playerPercentage = $status->max_players > 0 ? ($status->players / $status->max_players) * 100 : 0;

        if ($playerPercentage > 60) {
            $percentColor = 'error';
        } elseif ($playerPercentage > 30) {
            $percentColor = 'warning';
        }
    }
@endphp

@if ($displayMode === 'mode')
    @php
        $players = $status->players ?? 0;
        $maxPlayers = $status->max_players ?? 0;
        $map = $status->map ?? __($trans . '.player.unknown');
        $fillPercentage = $maxPlayers > 0 ? ($players / $maxPlayers) * 100 : 0;
        $isFull = $players >= $maxPlayers && $maxPlayers > 0;
        $isEmpty = $players == 0;
        $connect = $server->getConnectionString();
        $steamConnect = 'steam://connect/' . $server->ip . ':' . $server->port;
        $mapPreview = $service->getMapPreview($status ?? null);

        $loadClass = 'load-low';
        if ($fillPercentage > 75) {
            $loadClass = 'load-high';
        } elseif ($fillPercentage > 40) {
            $loadClass = 'load-medium';
        }
    @endphp

    <div class="monitoring-card-mode {{ $isFull ? 'server-full' : '' }} {{ $isEmpty ? 'server-empty' : '' }}">
        <div class="server-info">
            <div class="server-header">
                <div class="server-header-content">
                    <div class="server-name">{{ __($server->name) }}</div>
                    <div class="server-details">
                        <div class="server-stats">
                            <div class="server-map">
                                <img src="{{ $mapPreview }}" alt="{{ $map }}" loading="lazy" />
                            </div>
                            <div class="server-players {{ $isFull ? 'is-full' : ($players > 0 ? 'has-players' : '') }}"
                                @if (!isset($hideModal) || !$hideModal) onclick="openModal('server-details-{{ $server->id }}')" @endif>
                                {{ $players }}/{{ $maxPlayers }}
                            </div>
                            <div class="server-map-name">
                                {{ $map }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="server-actions">
                    <button class="server-action-btn copy-ip" data-copy="connect {{ $connect }}"
                        onclick="notyf.success('{{ __($trans . '.actions.copy_ip_success') }}')"
                        data-tooltip="{{ __($trans . '.actions.copy_ip') }}">
                        <x-icon path="ph.regular.copy" />
                    </button>
                    @if (!$isInactive)
                        <a href="{{ $steamConnect }}" class="connect-button"
                            data-tooltip="{{ __($trans . '.actions.play') }}">
                            <x-icon path="ph.regular.play" />
                        </a>
                    @endif
                </div>
            </div>
            <div class="server-load {{ $loadClass }}">
                <div class="server-load-bar">
                    <div class="server-load-fill" style="width: {{ $fillPercentage }}%"></div>
                </div>
            </div>
        </div>
    </div>

    @if (!isset($hideModal) || !$hideModal)
        <x-modal id="server-details-{{ $server->id }}" title="{{ __($server->name) }}" class="server-details-modal"
            loadUrl="{{ url('api/monitoring/server/' . $server->id) }}">
            <x-slot name="skeleton">
                @include('monitoring::server-details-skeleton')
            </x-slot>
        </x-modal>
    @endif
@else
    <article class="monitoring-card {{ $isInactive ? 'monitoring-card-inactive' : '' }}">
        @if (!$isInactive || $displayMode === 'standard')
            <figure class="monitoring-card-image">
                <img src="{{ $isInactive ? $service->getMapPreview(null) : $service->getMapPreview($status) }}"
                    loading="lazy"
                    alt="{{ $isInactive ? __($trans . '.player.unknown') : $status->map ?? __($trans . '.player.unknown') }} map preview">
            </figure>
        @endif

        <div class="monitoring-card-info">
            <div class="monitoring-card-info-block">
                <h3 class="monitoring-card-title">
                    {{ __($server->name) }}
                    @if ($isInactive || $hasError)
                        <span class="monitoring-card-error-icon"
                            title="{{ $isInactive ? __($trans . '.status_icon.inactive') : __($trans . '.status_icon.error') }}"
                            data-tooltip="{{ $isInactive ? __($trans . '.status_icon.inactive') : __($trans . '.status_icon.error') }}">
                            <x-icon path="ph.regular.warning-circle" />
                        </span>
                    @endif
                </h3>

                <ul class="monitoring-card-badges">
                    <li class="monitoring-card-badges-badge ip copyable"
                        data-tooltip="{{ __($trans . '.actions.copy_ip') }}"
                        data-copy="connect {{ $server->getConnectionString() }}">
                        {{ $isInactive && isset($server->display_ip) ? $server->display_ip : $server->getConnectionString() }}
                    </li>
                    @if (!$isInactive && !$hasError && ($displayMode === 'standard' || $displayMode === 'compact'))
                        <li class="monitoring-card-badges-badge map">
                            {{ $status->map ?? __($trans . '.player.unknown') }}
                        </li>
                    @endif
                    @if (($isInactive || $hasError) && $displayMode !== 'ultracompact')
                        <li class="monitoring-card-badges-badge status offline">{{ __($trans . '.offline') }}</li>
                    @endif
                </ul>

                @if (!$isInactive)
                    <div class="monitoring-card-players">
                        <p>{{ __($trans . '.players') }}:</p>
                        <p class="monitoring-card-players-total">{{ $status->players }} <span>/
                                {{ $status->max_players }}</span></p>
                    </div>
                @endif

                <div class="monitoring-card-buttons">
                    @if (!$isInactive && $displayMode !== 'ultracompact')
                        <x-button type="primary" size="small"
                            onclick="navigator.clipboard?.writeText('connect {{ $server->ip }}:{{ $server->port }}').catch(()=>{}); window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
                            <x-icon path="ph.regular.play" />{{ __($trans . '.actions.play') }}
                        </x-button>
                        @if (!isset($hideModal) || !$hideModal)
                            <x-button type="outline-primary" size="small" class="monitoring-card-buttons-more"
                                onclick="openModal('server-details-{{ $server->id }}')">
                                {{ __($trans . '.actions.more') }}
                                <x-icon path="ph.regular.dots-three" />
                            </x-button>
                        @endif
                    @elseif(!$isInactive && $displayMode === 'ultracompact')
                        <x-button type="primary" size="small" data-tooltip="{{ __($trans . '.actions.play') }}"
                            onclick="navigator.clipboard?.writeText('connect {{ $server->ip }}:{{ $server->port }}').catch(()=>{}); window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
                            <x-icon path="ph.regular.play" />
                        </x-button>
                        @if (!isset($hideModal) || !$hideModal)
                            <x-button type="outline-primary" size="small" class="monitoring-card-buttons-more"
                                data-tooltip="{{ __($trans . '.actions.more') }}"
                                onclick="openModal('server-details-{{ $server->id }}')">
                                <x-icon path="ph.regular.dots-three" />
                            </x-button>
                        @endif
                    @else
                        <x-button type="outline-primary" size="small" class="monitoring-card-buttons-more"
                            onclick="openModal('server-details-{{ $server->id }}')">
                            {{ $displayMode === 'ultracompact' ? '' : __($trans . '.actions.more') }}
                            @if ($displayMode === 'ultracompact')
                                <x-icon path="ph.regular.dots-three" />
                            @endif
                        </x-button>
                    @endif
                </div>
            </div>

            @if (!$isInactive)
                <div class="monitoring-card-progress" role="progressbar" aria-valuenow="{{ $playerPercentage }}"
                    aria-valuemin="0" aria-valuemax="100">
                    <div class="monitoring-card-progress-bar {{ $percentColor }}"
                        style="height: {{ $playerPercentage }}%">
                    </div>
                </div>
            @endif
        </div>
    </article>

    @if (!isset($hideModal) || !$hideModal)
        <x-modal id="server-details-{{ $server->id }}" title="{{ __($server->name) }}" class="server-details-modal"
            loadUrl="{{ url('api/monitoring/server/' . $server->id) }}">
            <x-slot name="skeleton">
                @include('monitoring::server-details-skeleton')
            </x-slot>
        </x-modal>
    @endif
@endif
