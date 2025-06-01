@php
    $trans = 'monitoring.server';
@endphp

<div class="monitoring-table-wrapper">
    <table class="monitoring-table">
        <tbody>
            @foreach ($servers as $serverData)
                @php
                    $server = $serverData['server'];
                    $status = $serverData['status'];
                    $isInactive = $serverData['isInactive'] ?? false;
                    $hasError = isset($status->online) && !$status->online;
                    $showActions = !$isInactive && !$hasError;

                    $statusClass = 'inactive';
                    $statusText = __($trans . '.inactive');

                    if (!$isInactive) {
                        if ($hasError) {
                            $statusClass = 'error';
                            $statusText = __($trans . '.error');
                        } else {
                            $statusClass = 'online';
                            $statusText = __($trans . '.online');
                        }
                    }
                @endphp
                <tr class="monitoring-table-row {{ $isInactive ? 'inactive' : '' }} {{ $hasError ? 'error' : '' }}">
                    <td class="server-name-cell">
                        <div class="server-name-container">
                            @if (!$isInactive && !$hasError)
                                <div class="server-icon">
                                    <img src="{{ $service->getMapPreview($status) }}"
                                        alt="{{ $status->map ?? __($trans . '.player.unknown') }} preview">
                                </div>
                            @endif
                            <div class="server-info">
                                <h4 class="server-title">{{ $server->name }}</h4>
                                <div class="server-ip copyable" data-tooltip="{{ __($trans . '.actions.copy_ip') }}"
                                    data-copy="{{ $server->ip }}:{{ $server->port }}"
                                    onclick="notyf.success('{{ __($trans . '.actions.copy_ip_success') }}')">
                                    {{ $server->getConnectionString() }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="server-map-cell">
                        <span class="server-map-container">
                            @if (!$isInactive && !$hasError)
                                {{ $status->map ?? __($trans . '.player.unknown') }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </span>
                    </td>
                    <td class="server-players-cell text-center">
                        @if (!$isInactive && !$hasError)
                            <div class="player-count">
                                <span class="current">{{ $status->players }}</span>
                                <span class="separator">/</span>
                                <span class="max">{{ $status->max_players }}</span>
                            </div>
                            @if ($status->max_players > 0)
                                @php
                                    $playerPercentage = ($status->players / $status->max_players) * 100;
                                    $progressColor = 'success';

                                    if ($playerPercentage > 60) {
                                        $progressColor = 'error';
                                    } elseif ($playerPercentage > 30) {
                                        $progressColor = 'warning';
                                    }
                                @endphp
                                <div class="player-progress-container">
                                    <div class="player-progress">
                                        <div class="player-progress-bar {{ $progressColor }}"
                                            style="width: {{ $playerPercentage }}%"></div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <span class="text-muted">0 / 0</span>
                        @endif
                    </td>
                    <td class="server-status-cell text-center">
                        <div class="server-status-indicator {{ $statusClass }}">
                            <span class="server-status-indicator-dot"></span>
                            <span class="server-status-indicator-text">{{ $statusText }}</span>
                        </div>
                    </td>
                    <td class="server-actions-cell text-center">
                        <div class="server-actions-container">
                            @if ($showActions)
                                <x-button type="primary" size="small" class="connect-button"
                                    onclick="window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'"
                                    data-tooltip="{{ __($trans . '.actions.play') }}">
                                    <x-icon path="ph.regular.play" />
                                </x-button>
                            @endif
                            <x-button type="outline-primary" size="small" class="details-button"
                                onclick="openModal('server-details-{{ $server->id }}')"
                                data-tooltip="{{ __($trans . '.actions.more') }}">
                                <x-icon path="ph.regular.dots-three" />
                            </x-button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@foreach ($servers as $serverData)
    @php
        $server = $serverData['server'];
    @endphp
    <x-modal id="server-details-{{ $server->id }}" title="{{ $server->name }}" class="server-details-modal"
        loadUrl="{{ url('api/monitoring/server/' . $server->id) }}">
        <x-slot name="skeleton">
            @include('monitoring::server-details-skeleton')
        </x-slot>
    </x-modal>
@endforeach
