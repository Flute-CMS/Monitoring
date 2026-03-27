@php
    $service = $app->get('monitoring.service');
    $server = $serverData['server'];
    $status = $serverData['status'];
    $isInactive = !isset($status->online) || !$status->online;
    $showPing = $showPing ?? true;
    $userGeo = $showPing ? $service->getUserCoordinates() : null;
@endphp

<head>
    @at(mm('Monitoring', 'Resources/assets/js/monitoring.js'))
</head>

<div class="monitoring-container monitoring-single-server"
    @if ($userGeo) data-user-lat="{{ $userGeo['lat'] }}" data-user-lon="{{ $userGeo['lon'] }}" @endif>
    <div class="monitoring-mode-{{ $displayMode ?? 'standard' }}">
        <div class="monitoring-single-server-content">
            @include('monitoring::components.server-card', [
                'server' => $server,
                'status' => $status,
                'displayMode' => $displayMode ?? 'standard',
                'isInactive' => $isInactive,
                'hideModal' => $hideModal ?? false,
                'showPing' => $showPing ?? true,
            ])
        </div>
    </div>
</div>
