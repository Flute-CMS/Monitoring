@php
    $additionalData = !empty($status->additional) ? json_decode($status->additional, true) : null;
    $isCsGo = $status->game === '730' && !empty($additionalData['players']);
    $showPing = filter_var(
        $showPing ?? \Flute\Modules\Monitoring\Services\MonitoringService::isPingEnabled(),
        FILTER_VALIDATE_BOOLEAN,
    );
@endphp

<head>
    @at(mm('Monitoring', 'Resources/assets/js/monitoring.js'))
</head>

@if ($isCsGo)
    @include('monitoring::components.server-full-details', [
        'server' => $server,
        'status' => $status,
        'showPing' => $showPing,
    ])
@else
    @include('monitoring::components.server-partial-details', [
        'server' => $server,
        'status' => $status,
        'showPing' => $showPing,
    ])
@endif
