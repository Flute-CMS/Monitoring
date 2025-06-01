@php
    $service = $app->get('monitoring.service');
    $hasError = isset($status->online) && !$status->online;
    $trans = 'monitoring.server';
    $serverId = $server->id;
    $isCsGo = $status->game === '730' && !empty($status->additional);
    $additionalData = $isCsGo ? json_decode($status->additional, true) : null;
@endphp

<head>
    @at(mm('Monitoring', 'Resources/assets/js/monitoring.js'))
</head>

@if ($isCsGo)
    @include('monitoring::components.server-full-details', ['server' => $server, 'status' => $status])
@else
    @include('monitoring::components.server-partial-details', ['server' => $server, 'status' => $status])
@endif
