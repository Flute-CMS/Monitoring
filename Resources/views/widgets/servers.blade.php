@php
    $service = $app->get('monitoring.service');

    $trans = 'monitoring.server';

    $hideInactive = isset($hideInactive) ? filter_var($hideInactive, FILTER_VALIDATE_BOOLEAN) : false;
    $limit = (int) ($limit ?? 50);
    $displayMode = $displayMode ?? 'standard';
    $showCountPlayers = isset($showCountPlayers) ? filter_var($showCountPlayers, FILTER_VALIDATE_BOOLEAN) : true;
    $showPlaceholders = isset($showPlaceholders) ? filter_var($showPlaceholders, FILTER_VALIDATE_BOOLEAN) : true;

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

<div class="monitoring-container">
    <header class="monitoring-header">
        <h2>{{ __($trans . '.our_servers') }} <small class="text-muted">({{ $totalServers }})</small></h2>

        @if ($showCountPlayers)
            <div class="monitoring-total">
                <div class="monitoring-total-info">
                    <p>{{ __($trans . '.player.total') }}:</p>
                    <p class="monitoring-total-count">{{ $totalPlayers['players'] }} <span>/
                            {{ $totalPlayers['max_players'] }}</span></p>
                </div>
                <div class="monitoring-total-progress" role="progressbar"
                    aria-valuenow="{{ $totalPlayers['max_players'] > 0 ? ($totalPlayers['players'] / $totalPlayers['max_players']) * 100 : 0 }}"
                    aria-valuemin="0" aria-valuemax="100">
                    <div class="monitoring-total-progress-bar"
                        style="width: {{ $totalPlayers['max_players'] > 0 ? ($totalPlayers['players'] / $totalPlayers['max_players']) * 100 : 0 }}%">
                    </div>
                </div>
            </div>
        @endif
    </header>

    @if ($displayMode === 'table')
        @if (count($tableServers) === 0)
            <div class="monitoring-empty">
                <p>{{ __($trans . '.no_servers') }}</p>
            </div>
        @else
            @include('monitoring::components.server-table', ['servers' => $tableServers, 'service' => $service])
        @endif
    @else
        <div class="monitoring-grid monitoring-mode-{{ $displayMode }} mt-3">
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
                    ])
                @endforeach

                @foreach ($displayInactiveServers as $serverData)
                    @include('monitoring::components.server-card', [
                        'server' => $serverData['server'],
                        'status' => $serverData['status'],
                        'displayMode' => $displayMode,
                        'isInactive' => true,
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
