@php
    $service = $app->get('monitoring.service');
    $hasError = isset($status->online) && !$status->online;
    $trans = 'monitoring.server';
    $serverId = $server->id;
    $additionalData = !empty($status->additional) ? json_decode($status->additional, true) : null;
    $isCsGo = $status->game === '730' && !empty($additionalData['players']);
@endphp

<head>
    @at(mm('Monitoring', 'Resources/assets/js/monitoring.js'))
</head>

@if ($isCsGo)
    @include('monitoring::components.server-full-details', ['server' => $server, 'status' => $status])
@else
    @include('monitoring::components.server-partial-details', ['server' => $server, 'status' => $status])
@endif
