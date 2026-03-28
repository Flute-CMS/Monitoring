@php
    $monitoringService = app('monitoring.service');
    $totalData = $monitoringService->getTotalPlayersCount();
    $playersCount = $totalData['players'];
    $maxPlayers = $totalData['max_players'];
    $allServers = $monitoringService->getAllServers();
    $enabledServers = array_filter($allServers, fn($s) => $s['server']->enabled);
    $activeCount = count(array_filter($enabledServers, fn($s) => $s['status']->online));
    $totalCount = count($enabledServers);
    $isOnline = $playersCount > 0;
    $fillPercent = ($maxPlayers > 0) ? min(round(($playersCount / $maxPlayers) * 100), 100) : 0;
    $fillLevel = match(true) {
        $fillPercent >= 75 => 'hot',
        $fillPercent >= 40 => 'warm',
        default => 'cool',
    };
    $onlineServersTip = array_values(array_filter($enabledServers, fn($s) => $s['status']->online));
@endphp

<span class="monitoring-pill {{ $isOnline ? 'monitoring-pill--online monitoring-pill--' . $fillLevel : 'monitoring-pill--empty' }}"
    data-pill-max="{{ $maxPlayers }}"
    data-tooltip="#monitoring-pill-tooltip" data-tooltip-placement="bottom">
    <span class="monitoring-pill__section">
        <span class="monitoring-pill__dot"></span>
        <x-icon path="ph.bold.users-bold" class="monitoring-pill__section-icon" />
        <span class="monitoring-pill__count" data-monitoring-count>{{ $playersCount }}</span>
    </span>
    <span class="monitoring-pill__sep"></span>
    <span class="monitoring-pill__section">
        <x-icon path="ph.bold.hard-drives-bold" class="monitoring-pill__section-icon" />
        <span class="monitoring-pill__servers" data-monitoring-servers>{{ $activeCount }}<span class="monitoring-pill__servers-dim">/{{ $totalCount }}</span></span>
    </span>
</span>

<div id="monitoring-pill-tooltip" style="display:none">
    <div class="mp-tip">
        <div class="mp-tip__header">
            <span class="mp-tip__label">{{ __('monitoring.total_online.players_online') }}</span>
            <div class="mp-tip__total">
                <span class="mp-tip__total-count">{{ $playersCount }}</span>
                <span class="mp-tip__total-sep">/</span>
                <span class="mp-tip__total-max">{{ $maxPlayers }}</span>
            </div>
        </div>
        @if (count($onlineServersTip) > 0)
            <div class="mp-tip__list" role="list">
                @foreach ($onlineServersTip as $srv)
                    @php
                        $server = $srv['server'];
                        $status = $srv['status'];
                        $connect = $server->getConnectionString();
                        $isSteamGame = !in_array($server->mod, ['minecraft', 'gta5', 'samp'], true);
                        $copyText = in_array($server->mod, ['730', '240', '10'], true) ? 'connect ' . $connect : $connect;
                        $mapPreview = $monitoringService->getMapPreview($status);
                        $srvFill = $status->max_players > 0 ? round(($status->players / $status->max_players) * 100) : 0;
                        $srvLevel = match(true) {
                            $srvFill >= 90 => 'full',
                            $srvFill >= 60 => 'hot',
                            $srvFill >= 30 => 'warm',
                            default => 'cool',
                        };
                    @endphp
                    <div class="mp-tip__srv mp-tip__srv--{{ $srvLevel }}" role="listitem">
                        @if ($mapPreview)
                            <div class="mp-tip__srv-bg" aria-hidden="true">
                                <img src="{{ $mapPreview }}" alt="" loading="lazy">
                            </div>
                            <div class="mp-tip__srv-overlay" aria-hidden="true"></div>
                        @endif
                        <div class="mp-tip__srv-inner">
                            <span class="mp-tip__srv-info">
                                <span class="mp-tip__srv-name">{{ $server->name }}</span>
                                @if ($status->map)
                                    <span class="mp-tip__srv-map">{{ $status->map }}</span>
                                @endif
                            </span>
                            <span class="mp-tip__srv-right">
                                <span class="mp-tip__srv-players">
                                    <span class="mp-tip__srv-players-bar" style="--fill: {{ $srvFill }}%"></span>
                                    <span class="mp-tip__srv-players-text">{{ $status->players }}<span class="mp-tip__srv-players-max">/{{ $status->max_players }}</span></span>
                                </span>
                                <span class="mp-tip__srv-actions">
                                    <span class="mp-tip__srv-btn" data-copy="{{ $copyText }}" title="{{ __('monitoring.server.actions.copy_ip') }}">
                                        <x-icon path="ph.bold.copy-bold" />
                                    </span>
                                    @if ($isSteamGame)
                                        <a class="mp-tip__srv-btn mp-tip__srv-btn--play" href="steam://connect/{{ $server->ip }}:{{ $server->port }}" title="{{ __('monitoring.server.actions.play') }}">
                                            <x-icon path="ph.bold.play-bold" />
                                        </a>
                                    @endif
                                </span>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('htmx:afterSwap', function(evt) {
        const pill = document.querySelector('.monitoring-pill');
        const countEl = document.querySelector('[data-monitoring-count]');
        const count = evt.detail.xhr.getResponseHeader('Monitoring-count');

        if (!pill || !countEl || typeof count !== 'string' || count === '') {
            return;
        }

        const numericCount = Number.parseInt(count, 10) || 0;
        const maxCount = Number.parseInt(pill.dataset.pillMax, 10) || 1;
        const fill = Math.min(Math.round((numericCount / maxCount) * 100), 100);
        const level = fill >= 75 ? 'hot' : fill >= 40 ? 'warm' : 'cool';

        countEl.textContent = numericCount;
        pill.classList.remove('monitoring-pill--cool', 'monitoring-pill--warm', 'monitoring-pill--hot');
        pill.classList.toggle('monitoring-pill--online', numericCount > 0);
        pill.classList.toggle('monitoring-pill--empty', numericCount <= 0);
        if (numericCount > 0) pill.classList.add('monitoring-pill--' + level);
    });
</script>
