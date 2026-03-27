@php
    $service = $app->get('monitoring.service');

    $trans = 'monitoring.server';

    $hideInactive = isset($hideInactive) ? filter_var($hideInactive, FILTER_VALIDATE_BOOLEAN) : false;
    $limit = (int) ($limit ?? 50);
    $displayMode = $displayMode ?? 'standard';
    $showCountPlayers = isset($showCountPlayers) ? filter_var($showCountPlayers, FILTER_VALIDATE_BOOLEAN) : true;
    $showPlaceholders = isset($showPlaceholders) ? filter_var($showPlaceholders, FILTER_VALIDATE_BOOLEAN) : true;
    $showPing = isset($showPing) ? filter_var($showPing, FILTER_VALIDATE_BOOLEAN) : true;
    $userGeo = $showPing ? $service->getUserCoordinates() : null;

    $limitedActiveServers = array_slice($activeServers, 0, $limit);
    $displayInactiveServers = !$hideInactive
        ? array_slice($inactiveServers, 0, $limit - count($limitedActiveServers))
        : [];
    $displayTotalServers = count($limitedActiveServers) + count($displayInactiveServers);
    
    $tableServers = [];
    foreach ($limitedActiveServers as $serverData) {
        $tableServers[] = [
            'server' => $serverData['server'],
            'status' => $serverData['status'],
            'isInactive' => false
        ];
    }
    
    if (!$hideInactive) {
        foreach ($displayInactiveServers as $serverData) {
            $tableServers[] = [
                'server' => $serverData['server'],
                'status' => $serverData['status'],
                'isInactive' => true
            ];
        }
    }
@endphp

<head>
    @at(mm('Monitoring', 'Resources/assets/js/monitoring.js'))
</head>

<div class="monitoring-container"
    @if ($userGeo) data-user-lat="{{ $userGeo['lat'] }}" data-user-lon="{{ $userGeo['lon'] }}" @endif>
    <header class="monitoring-header">
        <div class="monitoring-header__left">
            <h2 class="monitoring-header__title">
                {{ __($trans . '.our_servers') }}
            </h2>
            <div class="monitoring-header__meta">
                <span class="monitoring-live-badge">
                    <span class="monitoring-live-badge__dot"></span>
                    {{ __($trans . '.live') }}
                </span>
                <span class="monitoring-header__server-count">{{ $totalServers }} {{ __($trans . '.servers_count') }}</span>
            </div>
        </div>

        @if ($showCountPlayers)
            <div class="monitoring-total">
                <span class="monitoring-total-label">{{ __($trans . '.player.total') }}</span>
                <span class="monitoring-total-num">
                    {{ $totalPlayers['players'] }} <span>/ {{ $totalPlayers['max_players'] }}</span>
                </span>
            </div>
        @endif
    </header>

    @if ($displayMode === 'table')
        @if (count($tableServers) === 0)
            <div class="monitoring-empty">
                <p>{{ __($trans . '.no_servers') }}</p>
            </div>
        @else
            @include('monitoring::components.server-table', ['servers' => $tableServers, 'service' => $service, 'showPing' => $showPing])
        @endif
    @else
        <div class="monitoring-grid monitoring-mode-{{ $displayMode }}">
            @if (count($limitedActiveServers) === 0 && count($displayInactiveServers) === 0)
                <div class="monitoring-empty">
                    <p>{{ __($trans . '.no_servers') }}</p>
                </div>
            @else
                @foreach ($limitedActiveServers as $serverData)
                    @include('monitoring::components.server-card', [
                        'server' => $serverData['server'],
                        'status' => $serverData['status'],
                        'displayMode' => $displayMode,
                        'isInactive' => false,
                        'showPing' => $showPing,
                    ])
                @endforeach

                @foreach ($displayInactiveServers as $serverData)
                    @include('monitoring::components.server-card', [
                        'server' => $serverData['server'],
                        'status' => $serverData['status'],
                        'displayMode' => $displayMode,
                        'isInactive' => true,
                        'showPing' => $showPing,
                    ])
                @endforeach

                @php
                    $totalDisplayServersCount = count($limitedActiveServers) + count($displayInactiveServers);
                    $minCardsPerRow = 3;
                    $remainder = $totalDisplayServersCount % $minCardsPerRow;
                    $emptyCardsCount = $remainder > 0 ? $minCardsPerRow - $remainder : 0;
                @endphp

                @if ($showPlaceholders)
                    @for ($i = 0; $i < $emptyCardsCount; $i++)
                        <div class="monitoring-empty-hide">
                            <div class="monitoring-empty-card"></div>
                        </div>
                    @endfor
                @endif
            @endif
        </div>
    @endif
</div>
