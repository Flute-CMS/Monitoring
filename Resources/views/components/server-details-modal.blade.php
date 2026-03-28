@php
    $showPing = filter_var(
        $showPing ?? \Flute\Modules\Monitoring\Services\MonitoringService::isPingEnabled(),
        FILTER_VALIDATE_BOOLEAN,
    );
@endphp
<x-modal id="server-details-{{ $server->id }}" title="{{ __($server->name) }}" class="server-details-modal"
    loadUrl="{{ url('api/monitoring/server/' . $server->id)->addParams(['show_ping' => $showPing ? 1 : 0]) }}">
    <x-slot name="skeleton">
        @include('monitoring::server-details-skeleton', ['showPing' => $showPing])
    </x-slot>
</x-modal>
