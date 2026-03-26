@php
    $trans = 'monitoring.total_online';
    $pct = $totalPlayers['max_players'] > 0
        ? round(($totalPlayers['players'] / $totalPlayers['max_players']) * 100)
        : 0;
@endphp

<div class="total-online">
    <div class="total-online__top">
        <div class="total-online__left">
            <span class="total-online__label">{{ __($trans . '.players_online') }}</span>
            <div class="total-online__num">
                {{ $totalPlayers['players'] }}
                <span>/ {{ $totalPlayers['max_players'] }}</span>
            </div>
        </div>
        <div class="total-online__right">
            <span class="total-online__pct">{{ $pct }}%</span>
            <span class="total-online__servers">
                {{ __($trans . '.servers_online', ['active' => $activeServersCount, 'total' => $totalServersCount ?? $activeServersCount]) }}
            </span>
        </div>
    </div>
    <div class="total-online__bar">
        <div class="total-online__bar-fill" style="width: {{ $pct }}%"></div>
    </div>
</div>
