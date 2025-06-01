@php
    $service = $app->get('monitoring.service');
    $trans = 'monitoring.server';
    $displayMode = $displayMode ?? 'standard';
    $hasError = isset($status->online) && ! $status->online;
    $isInactive = isset($isInactive) ? $isInactive : false;
    $percentColor = 'success';

    if (! $isInactive && isset($status->max_players)) {
        $playerPercentage = $status->max_players > 0 ? ($status->players / $status->max_players) * 100 : 0;

        if ($playerPercentage > 60) {
            $percentColor = 'error';
        } elseif ($playerPercentage > 30) {
            $percentColor = 'warning';
        }
    }
@endphp

<article class="monitoring-card {{ $isInactive ? 'monitoring-card-inactive' : '' }}">
    @if(! $isInactive || $displayMode === 'standard')
        <figure class="monitoring-card-image">
            <img src="{{ $isInactive ? $service->getMapPreview(null) : $service->getMapPreview($status) }}" loading="lazy"
                alt="{{ $isInactive ? __($trans.'.player.unknown') : ($status->map ?? __($trans.'.player.unknown')) }} map preview">
        </figure>
    @endif

    <div class="monitoring-card-info">
        <div class="monitoring-card-info-block">
            <h3 class="monitoring-card-title">
                {{ $server->name }}
                @if ($isInactive || $hasError)
                    <span class="monitoring-card-error-icon"
                        title="{{ $isInactive ? __($trans.'.status_icon.inactive') : __($trans.'.status_icon.error') }}"
                        data-tooltip="{{ $isInactive ? __($trans.'.status_icon.inactive') : __($trans.'.status_icon.error') }}">
                        <x-icon path="ph.regular.warning-circle" />
                    </span>
                @endif
            </h3>

            <ul class="monitoring-card-badges">
                <li class="monitoring-card-badges-badge ip">
                    {{ $isInactive && isset($server->display_ip) ? $server->display_ip : $server->getConnectionString() }}
                </li>
                @if(! $isInactive && ($displayMode === 'standard' || $displayMode === 'compact'))
                    <li class="monitoring-card-badges-badge map">
                        {{ $status->map ?? __($trans.'.player.unknown') }}
                    </li>
                @endif
                @if($isInactive && $displayMode !== 'ultracompact')
                    <li class="monitoring-card-badges-badge status">{{ __($trans.'.inactive') }}</li>
                @endif
            </ul>

            @if(! $isInactive)
                <div class="monitoring-card-players">
                    <p>{{ __($trans.'.players') }}:</p>
                    <p class="monitoring-card-players-total">{{ $status->players }} <span>/
                            {{ $status->max_players }}</span></p>
                </div>
            @endif

            <div class="monitoring-card-buttons">
                @if(! $isInactive && $displayMode !== 'ultracompact')
                    <x-button type="primary" size="small"
                        onclick="window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
                        <x-icon path="ph.regular.play" />{{  __($trans.'.actions.play') }}
                    </x-button>
                    @if(! isset($hideModal) || ! $hideModal)
                        <x-button type="outline-primary" size="small" class="monitoring-card-buttons-more"
                            onclick="openModal('server-details-{{ $server->id }}')">
                            {{  __($trans.'.actions.more') }}
                            <x-icon path="ph.regular.dots-three" />
                        </x-button>
                    @endif
                @elseif(! $isInactive && $displayMode === 'ultracompact')
                    <x-button type="primary" size="small" data-tooltip="{{ __($trans.'.actions.play') }}"
                        onclick="window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
                        <x-icon path="ph.regular.play" />
                    </x-button>
                    @if(! isset($hideModal) || ! $hideModal)
                        <x-button type="outline-primary" size="small" class="monitoring-card-buttons-more"
                            data-tooltip="{{ __($trans.'.actions.more') }}"
                            onclick="openModal('server-details-{{ $server->id }}')">
                            <x-icon path="ph.regular.dots-three" />
                        </x-button>
                    @endif
                @else
                    <x-button type="outline-primary" size="small" class="monitoring-card-buttons-more"
                        onclick="openModal('server-details-{{ $server->id }}')">
                        {{ $displayMode === 'ultracompact' ? '' : __($trans.'.actions.more') }}
                        @if($displayMode === 'ultracompact')
                            <x-icon path="ph.regular.dots-three" />
                        @endif
                    </x-button>
                @endif
            </div>
        </div>

        @if(! $isInactive)
            <div class="monitoring-card-progress" role="progressbar" aria-valuenow="{{ $playerPercentage }}"
                aria-valuemin="0" aria-valuemax="100">
                <div class="monitoring-card-progress-bar {{ $percentColor }}" style="height: {{ $playerPercentage }}%">
                </div>
            </div>
        @endif
    </div>
</article>

@if(! isset($hideModal) || ! $hideModal)
    <x-modal id="server-details-{{ $server->id }}" title="{{ $server->name }}" class="server-details-modal"
        loadUrl="{{ url('api/monitoring/server/'.$server->id) }}">
        <x-slot name="skeleton">
            @include('monitoring::server-details-skeleton')
        </x-slot>
    </x-modal>
@endif