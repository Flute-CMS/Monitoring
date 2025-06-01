@php
    $service = $app->get('monitoring.service');
    $hasError = isset($status->online) && !$status->online;
    $trans = 'monitoring.server';
    $serverId = $server->id;
@endphp

<div class="server-details">
    <div class="server-details-header">
        @if ($server->enabled && !$hasError)
            <div class="server-details-image">
                <img src="{{ $service->getMapPreview($status) }}"
                    alt="{{ $status->map ?? __($trans . '.player.unknown') }} map preview">
                <div class="server-details-image-map">{{ $status->map ?? __($trans . '.player.unknown') }}</div>
            </div>
        @endif

        <div class="server-details-info">
            <div class="server-details-info-grid">
                <div class="server-details-info-item">
                    <div class="server-details-info-item-label">{{ __($trans . '.ip') }}</div>
                    <div class="server-details-info-item-value copyable"
                        data-tooltip="{{ __($trans . '.actions.copy_ip') }}"
                        data-copy="{{ $server->getConnectionString() }}"
                        onclick="notyf.success('{{ __($trans . '.actions.copy_ip_success') }}')">
                        {{ $server->getConnectionString() }}
                    </div>
                </div>

                @if ($server->enabled && !$hasError)
                    <div class="server-details-info-item">
                        <div class="server-details-info-item-label">{{ __($trans . '.map') }}</div>
                        <div class="server-details-info-item-value">{{ $status->map ?? __($trans . '.player.unknown') }}
                        </div>
                    </div>
                @endif

                <div class="server-details-info-item">
                    <div class="server-details-info-item-label">{{ __($trans . '.players') }}</div>
                    <div class="server-details-info-item-value">
                        @if ($server->enabled && !$hasError)
                            <span class="player-count">{{ $status->players }} / {{ $status->max_players }}</span>
                        @else
                            <span class="player-count">0 / 0</span>
                        @endif
                    </div>
                </div>

                <div class="server-details-info-item">
                    <div class="server-details-info-item-label">{{ __($trans . '.status') }}</div>
                    <div class="server-details-info-item-status">
                        @if (!$server->enabled)
                            <span class="server-details-info-item-status-indicator inactive"></span>
                            <span>{{ __($trans . '.inactive') }}</span>
                        @elseif ($hasError)
                            <span class="server-details-info-item-status-indicator offline"></span>
                            <span>{{ __($trans . '.error') }}</span>
                        @else
                            <span class="server-details-info-item-status-indicator online"></span>
                            <span>{{ __($trans . '.online') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if (!$server->enabled)
                <div class="server-details-message">
                    <div class="server-details-message-text">
                        {{ __($trans . '.maintenance') }}
                    </div>
                </div>
            @elseif ($hasError)
                <div class="server-details-message">
                    <div class="server-details-message-text">
                        {{ __($trans . '.unavailable') }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($server->enabled && !$hasError)
        <div class="server-details-players">
            <div class="server-details-players-header">
                <h4>
                    {{ __($trans . '.player_list') }}
                    @if ($status->players > 0)
                        <span class="count">{{ $status->players }}</span>
                    @endif
                </h4>

                @if ($status->players > 5)
                    <div class="server-details-players-search">
                        <input type="text" placeholder="{{ __($trans . '.player.search') }}"
                            class="server-details-players-search-input">
                    </div>
                @endif
            </div>

            @if ($status->players > 0 && !empty($status->getPlayersData()))
                @php
                    $players = collect($status->getPlayersData());

                    $allEmpty = false;

                    if (!$players->isEmpty()) {
                        $allEmpty = $players->every(function ($player) {
                            return empty(trim($player['Name']));
                        });
                    }
                @endphp

                @if ($allEmpty && user()->can('admin.servers'))
                    <x-alert type="warning" class="server-details-players-empty" withClose="false">
                        {!! __($trans . '.empty_players_alert', ['url' => 'https://github.com/Source2ZE/ServerListPlayersFix']) !!}
                    </x-alert>
                @endif

                <div class="server-details-players-table-wrapper">
                    <table class="server-details-players-table">
                        <thead>
                            <tr>
                                <th>{{ __($trans . '.player.name') }}</th>
                                <th>{{ __($trans . '.player.score') }}</th>
                                <th>{{ __($trans . '.player.time') }}</th>
                            </tr>
                        </thead>
                        <tbody id="playerTableBody-{{ $serverId }}">

                            @foreach ($players as $player)
                                <tr class="player-row">
                                    <td class="player-name">{{ $player['Name'] ?: __($trans . '.player.unknown') }}
                                    </td>
                                    <td class="player-score">{{ $player['Score'] ?? '0' }}</td>
                                    <td class="player-time">
                                        {{ $player['Time'] ? gmdate('H:i:s', $player['Time']) : '00:00:00' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div id="noPlayersFound-{{ $serverId }}" class="server-details-empty" style="display: none;">
                    {{ __($trans . '.player.no_results') }}
                </div>
            @else
                <div class="server-details-empty">
                    {{ __($trans . '.no_players') }}
                </div>
            @endif
        </div>
    @endif
</div>

<div class="modal__footer modal__footer-server-details">
    <x-button type="outline-primary" class="w-100" data-a11y-dialog-hide="{{ 'server-details-' . $server->id }}">
        {{ __($trans . '.actions.close') }}
    </x-button>
    @if ($server->enabled && !$hasError)
        <x-button type="success" class="w-100"
            onclick="window.location='steam://connect/{{ $server->ip }}:{{ $server->port }}'">
            {{ __($trans . '.actions.play') }}
        </x-button>
    @endif
</div>
