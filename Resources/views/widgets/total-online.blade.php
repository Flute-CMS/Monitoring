@php
    $trans = 'monitoring.total_online';
    $progressPercentage =
        $totalPlayers['max_players'] > 0 ? ($totalPlayers['players'] / $totalPlayers['max_players']) * 100 : 0;
@endphp

<div class="total-online-widget-ultra-minimal">
    <div class="total-online-header">
        <div class="total-online-icon">
            <x-icon path="ph.regular.users-three" />
        </div>
        <div class="total-online-title">{{ __($trans . '.players_online') }}</div>
        <div class="total-online-servers-info">{{ $activeServersCount }} {{ __('monitoring.metrics.online_servers') }}
        </div>
    </div>

    <div class="total-online-stats">
        <div class="total-online-players">
            <span class="total-online-players-current">{{ $totalPlayers['players'] }}</span>
            <span class="total-online-players-separator">/</span>
            <span class="total-online-players-max">{{ $totalPlayers['max_players'] }}</span>
        </div>

        <div class="total-online-progress">
            <div class="total-online-progress-bar" style="width: {{ $progressPercentage }}%"></div>
        </div>
    </div>
</div>
