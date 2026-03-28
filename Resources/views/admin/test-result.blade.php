@php
    $success = $result['success'] ?? false;
    $error = $result['error'] ?? null;
    $data = $result['data'] ?? [];
    $time = $result['time'] ?? 0;
    
    $playersData = $data['players_data'] ?? [];
    if (is_string($playersData)) {
        $playersData = json_decode($playersData, true) ?? [];
    }
    
    $additionalData = $data['additional'] ?? [];
    if (is_string($additionalData)) {
        $additionalData = json_decode($additionalData, true) ?? [];
    }
    
    $isCsGo = ($data['game'] ?? '') === '730' && !empty($additionalData);
    $hasPlayers = !empty($playersData) && is_array($playersData);
    $hasRconPlayers = $isCsGo && !empty($additionalData['players']);
    $showPing = filter_var(
        $showPing ?? \Flute\Modules\Monitoring\Services\MonitoringService::isPingEnabled(),
        FILTER_VALIDATE_BOOLEAN,
    );
@endphp

<div class="mtest">
    <div class="mtest__header {{ $success ? 'mtest__header--success' : 'mtest__header--error' }}">
        <div class="mtest__status">
            @if ($success)
                <span class="mtest__icon mtest__icon--success">
                    <x-icon path="ph.bold.check-bold" />
                </span>
                <span>{{ __('monitoring.admin.test_success') }}</span>
            @else
                <span class="mtest__icon mtest__icon--error">
                    <x-icon path="ph.bold.x-bold" />
                </span>
                <span>{{ __('monitoring.admin.test_failed') }}</span>
            @endif
        </div>
        <div class="mtest__time">
            {{ number_format($time, 0) }} ms
        </div>
    </div>

    @if ($error && !$success)
        <div class="mtest__error">
            {{ $error }}
        </div>
    @endif

    @if (!empty($data))
        <div class="mtest__grid">
            <div class="mtest__card">
                <div class="mtest__card-icon">
                    <x-icon path="ph.regular.globe" />
                </div>
                <div class="mtest__card-content">
                    <div class="mtest__card-label">{{ __('monitoring.admin.ip_port') }}</div>
                    <div class="mtest__card-value">{{ $data['ip'] }}:{{ $data['port'] }}</div>
                </div>
            </div>

            @if ($success && ($data['online'] ?? false))
                <div class="mtest__card">
                    <div class="mtest__card-icon mtest__card-icon--success">
                        <x-icon path="ph.regular.users" />
                    </div>
                    <div class="mtest__card-content">
                        <div class="mtest__card-label">{{ __('monitoring.server.players') }}</div>
                        <div class="mtest__card-value">{{ $data['players'] ?? 0 }} / {{ $data['max_players'] ?? 0 }}</div>
                    </div>
                </div>

                <div class="mtest__card">
                    <div class="mtest__card-icon">
                        <x-icon path="ph.regular.map-trifold" />
                    </div>
                    <div class="mtest__card-content">
                        <div class="mtest__card-label">{{ __('monitoring.server.map') }}</div>
                        <div class="mtest__card-value">{{ $data['map'] ?? '-' }}</div>
                    </div>
                </div>

                @if (!empty($data['game']))
                    <div class="mtest__card">
                        <div class="mtest__card-icon">
                            <x-icon path="ph.regular.game-controller" />
                        </div>
                        <div class="mtest__card-content">
                            <div class="mtest__card-label">{{ __('monitoring.admin.game_id') }}</div>
                            <div class="mtest__card-value">{{ $data['game'] }}</div>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        @if ($isCsGo && isset($additionalData['score_ct']) && isset($additionalData['score_t']))
            <div class="mtest__scores">
                <div class="mtest__score mtest__score--ct">
                    <span class="mtest__score-label">CT</span>
                    <span class="mtest__score-value">{{ $additionalData['score_ct'] }}</span>
                </div>
                <div class="mtest__score-divider">:</div>
                <div class="mtest__score mtest__score--t">
                    <span class="mtest__score-value">{{ $additionalData['score_t'] }}</span>
                    <span class="mtest__score-label">T</span>
                </div>
            </div>
        @endif

        @if ($hasRconPlayers)
            <div class="mtest__section">
                <div class="mtest__section-header">
                    <x-icon path="ph.regular.users-three" />
                    {{ __('monitoring.server.player_list') }} ({{ count($additionalData['players']) }})
                </div>
                <div class="mtest__players">
                    @foreach ($additionalData['players'] as $player)
                        @php
                            $isT = ($player['team'] ?? 0) == 2;
                            $steamInfo = $player['steam_info'] ?? [];
                            $ping = $player['ping'] ?? 0;
                            $pingClass = $ping < 60 ? 'good' : ($ping < 120 ? 'medium' : 'bad');
                        @endphp
                        <div class="mtest__player {{ $isT ? 'mtest__player--t' : 'mtest__player--ct' }}">
                            <div class="mtest__player-main">
                                <div class="mtest__player-team {{ $isT ? 't' : 'ct' }}"></div>
                                <div class="mtest__player-name">{{ $steamInfo['name'] ?? $player['name'] ?? 'Unknown' }}</div>
                                @if (isset($player['prime']))
                                    <span class="mtest__player-prime {{ $player['prime'] ? 'prime' : '' }}" title="Prime">P</span>
                                @endif
                            </div>
                            <div class="mtest__player-stats">
                                <span class="mtest__player-stat" title="{{ __('monitoring.server.csgo.kills') }}">
                                    <x-icon path="ph.regular.sword" /> {{ $player['kills'] ?? 0 }}
                                </span>
                                <span class="mtest__player-stat" title="{{ __('monitoring.server.csgo.deaths') }}">
                                    <x-icon path="ph.regular.skull" /> {{ $player['death'] ?? 0 }}
                                </span>
                                @if (isset($player['headshots']))
                                    <span class="mtest__player-stat" title="{{ __('monitoring.server.csgo.headshots') }}">
                                        <x-icon path="ph.regular.crosshair" /> {{ $player['headshots'] }}
                                    </span>
                                @endif
                                @if ($showPing)
                                    <span class="mtest__player-ping mtest__player-ping--{{ $pingClass }}" title="{{ $ping }} ms">
                                        {{ $ping }}ms
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($hasPlayers)
            <div class="mtest__section">
                <div class="mtest__section-header">
                    <x-icon path="ph.regular.users-three" />
                    {{ __('monitoring.server.player_list') }} ({{ count($playersData) }})
                </div>
                <div class="mtest__players mtest__players--simple">
                    @foreach (array_slice($playersData, 0, 20) as $player)
                        <div class="mtest__player mtest__player--simple">
                            <span class="mtest__player-name">{{ $player['name'] ?? 'Unknown' }}</span>
                            @if (isset($player['score']))
                                <span class="mtest__player-score">{{ $player['score'] }}</span>
                            @endif
                            @if (isset($player['time']))
                                <span class="mtest__player-time">{{ gmdate('H:i:s', $player['time'] ?? 0) }}</span>
                            @endif
                        </div>
                    @endforeach
                    @if (count($playersData) > 20)
                        <div class="mtest__more">
                            {{ __('monitoring.admin.and_more', ['count' => count($playersData) - 20]) }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if (!empty($additionalData) && !$isCsGo)
            <details class="mtest__details">
                <summary>{{ __('monitoring.admin.additional_data') }}</summary>
                <pre>{{ json_encode($additionalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        @endif
    @endif
</div>

<style>
.mtest {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.mtest__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-radius: var(--border05);
    font-weight: 600;
}

.mtest__header--success {
    background: linear-gradient(135deg, rgba(var(--success-rgb), 0.15), rgba(var(--success-rgb), 0.05));
    border: 1px solid rgba(var(--success-rgb), 0.3);
}

.mtest__header--error {
    background: linear-gradient(135deg, rgba(var(--error-rgb), 0.15), rgba(var(--error-rgb), 0.05));
    border: 1px solid rgba(var(--error-rgb), 0.3);
}

.mtest__status {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.mtest__icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.mtest__icon--success {
    background: var(--success);
    color: #fff;
}

.mtest__icon--error {
    background: var(--error);
    color: #fff;
}

.mtest__time {
    font-family: var(--font-mono, monospace);
    font-size: 0.875rem;
    color: var(--text-400);
    padding: 0.25rem 0.75rem;
    background: var(--transp-05);
    border-radius: 999px;
}

.mtest__error {
    padding: 0.75rem 1rem;
    background: rgba(var(--error-rgb), 0.1);
    border-left: 3px solid var(--error);
    border-radius: 0 var(--border05) var(--border05) 0;
    color: var(--error);
    font-size: 0.875rem;
}

.mtest__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
}

.mtest__card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: var(--transp-05);
    border-radius: var(--border05);
    border: 1px solid var(--transp-1);
}

.mtest__card-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--transp-1);
    border-radius: 8px;
    color: var(--text-300);
    flex-shrink: 0;
}

.mtest__card-icon--success {
    background: rgba(var(--success-rgb), 0.15);
    color: var(--success);
}

.mtest__card-content {
    min-width: 0;
}

.mtest__card-label {
    font-size: 0.75rem;
    color: var(--text-400);
    margin-bottom: 0.125rem;
}

.mtest__card-value {
    font-weight: 600;
    font-size: 0.9375rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mtest__scores {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--transp-05);
    border-radius: var(--border05);
}

.mtest__score {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mtest__score-label {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.mtest__score--ct .mtest__score-label {
    background: rgba(33, 150, 243, 0.2);
    color: #2196F3;
}

.mtest__score--t .mtest__score-label {
    background: rgba(255, 193, 7, 0.2);
    color: #FFC107;
}

.mtest__score-value {
    font-size: 1.5rem;
    font-weight: 700;
}

.mtest__score-divider {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-400);
}

.mtest__section {
    background: var(--transp-05);
    border-radius: var(--border05);
    overflow: hidden;
}

.mtest__section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--transp-05);
    font-weight: 600;
    font-size: 0.875rem;
    border-bottom: 1px solid var(--transp-1);
}

.mtest__players {
    max-height: 400px;
    overflow-y: auto;
}

.mtest__player {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 1rem;
    border-bottom: 1px solid var(--transp-05);
    transition: background 0.15s;
}

.mtest__player:last-child {
    border-bottom: none;
}

.mtest__player:hover {
    background: var(--transp-05);
}

.mtest__player--ct {
    border-left: 3px solid #2196F3;
}

.mtest__player--t {
    border-left: 3px solid #FFC107;
}

.mtest__player-main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.mtest__player-team {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.mtest__player-team.ct {
    background: #2196F3;
}

.mtest__player-team.t {
    background: #FFC107;
}

.mtest__player-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.mtest__player-prime {
    font-size: 0.625rem;
    font-weight: 700;
    padding: 0.125rem 0.375rem;
    border-radius: 3px;
    background: var(--transp-1);
    color: var(--text-400);
}

.mtest__player-prime.prime {
    background: rgba(255, 193, 7, 0.2);
    color: #FFC107;
}

.mtest__player-stats {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.mtest__player-stat {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8125rem;
    color: var(--text-300);
}

.mtest__player-ping {
    font-size: 0.75rem;
    font-family: var(--font-mono, monospace);
    padding: 0.125rem 0.375rem;
    border-radius: 3px;
}

.mtest__player-ping--good {
    background: rgba(var(--success-rgb), 0.15);
    color: var(--success);
}

.mtest__player-ping--medium {
    background: rgba(255, 193, 7, 0.15);
    color: #FFC107;
}

.mtest__player-ping--bad {
    background: rgba(var(--error-rgb), 0.15);
    color: var(--error);
}

.mtest__players--simple .mtest__player {
    border-left: none;
}

.mtest__player--simple {
    padding: 0.5rem 1rem;
}

.mtest__player-score,
.mtest__player-time {
    font-size: 0.8125rem;
    color: var(--text-400);
    font-family: var(--font-mono, monospace);
}

.mtest__more {
    padding: 0.5rem 1rem;
    text-align: center;
    font-size: 0.8125rem;
    color: var(--text-400);
    background: var(--transp-05);
}

.mtest__details {
    background: var(--transp-05);
    border-radius: var(--border05);
}

.mtest__details summary {
    padding: 0.75rem 1rem;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--text-300);
}

.mtest__details summary:hover {
    color: var(--text-100);
}

.mtest__details pre {
    margin: 0;
    padding: 1rem;
    font-size: 0.75rem;
    background: var(--transp-1);
    border-radius: 0 0 var(--border05) var(--border05);
    overflow-x: auto;
    max-height: 300px;
}
</style>
