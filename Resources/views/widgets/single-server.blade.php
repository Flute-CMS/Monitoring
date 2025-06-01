@php
    $server = $serverData['server'];
    $status = $serverData['status'];
    $isInactive = ! isset($status->online) || ! $status->online;
@endphp

<div class="monitoring-container monitoring-single-server">
    <div class="monitoring-mode-{{ $displayMode ?? 'standard' }}">
        <div class="monitoring-single-server-content">
            @include('monitoring::components.server-card', [
                'server' => $server,
                'status' => $status,
                'displayMode' => $displayMode ?? 'standard',
                'isInactive' => $isInactive,
                'hideModal' => $hideModal ?? false
            ])
        </div>
    </div>
</div> 