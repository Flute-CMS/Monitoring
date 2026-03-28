<div class="monitoring-admin-servers">
    <div class="monitoring-admin-servers__list">
        @foreach ($servers as $server)
            @php
                $status = $serverStatuses[$server->id] ?? null;
                $isOnline = $status && $status->online;
            @endphp
            <div class="monitoring-admin-servers__item {{ $isOnline ? 'is-online' : 'is-offline' }}">
                <div class="monitoring-admin-servers__info">
                    <div class="monitoring-admin-servers__indicator {{ $isOnline ? 'online' : 'offline' }}"></div>
                    <div class="monitoring-admin-servers__details">
                        <span class="monitoring-admin-servers__name">{{ $server->name }}</span>
                        <span class="monitoring-admin-servers__ip">{{ $server->getConnectionString() }}</span>
                    </div>
                </div>
                <div class="monitoring-admin-servers__stats">
                    @if ($isOnline)
                        <span class="monitoring-admin-servers__players">
                            <x-icon path="ph.regular.users" />
                            {{ $status->players }}/{{ $status->max_players }}
                        </span>
                        <span class="monitoring-admin-servers__map" title="{{ $status->map }}">
                            <x-icon path="ph.regular.map-trifold" />
                            {{ \Illuminate\Support\Str::limit($status->map ?? '-', 15) }}
                        </span>
                    @else
                        <span class="monitoring-admin-servers__offline-text">
                            {{ __('monitoring.admin.offline') }}
                        </span>
                    @endif
                </div>
                <div class="monitoring-admin-servers__actions">
                    <a href="{{ url('/admin/monitoring/test/' . $server->id) }}" 
                       class="btn btn-sm btn-outline-primary" 
                       data-tooltip="{{ __('monitoring.admin.test_server') }}">
                        <x-icon path="ph.regular.play" />
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>

<style>
.monitoring-admin-servers__list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.monitoring-admin-servers__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--transp-05);
    border-radius: var(--border05);
    transition: var(--transition);
}

.monitoring-admin-servers__item:hover {
    background: var(--transp-1);
}

.monitoring-admin-servers__item.is-offline {
    opacity: 0.7;
}

.monitoring-admin-servers__info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.monitoring-admin-servers__indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.monitoring-admin-servers__indicator.online {
    background: var(--success);
    box-shadow: 0 0 6px var(--success);
}

.monitoring-admin-servers__indicator.offline {
    background: var(--error);
}

.monitoring-admin-servers__details {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.monitoring-admin-servers__name {
    font-weight: 600;
    font-size: var(--p-small);
}

.monitoring-admin-servers__ip {
    font-size: var(--small);
    color: var(--text-400);
    font-family: monospace;
}

.monitoring-admin-servers__stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.monitoring-admin-servers__players,
.monitoring-admin-servers__map {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: var(--small);
    color: var(--text-300);
}

.monitoring-admin-servers__offline-text {
    font-size: var(--small);
    color: var(--error);
}

.monitoring-admin-servers__actions {
    display: flex;
    gap: 0.5rem;
}
</style>
