<span class="navbar__logo-monitoring" data-tooltip="{{ __('monitoring.navbar.logo') }}">
    <span class="navbar__logo-monitoring-indicator"></span>
    {{ app('monitoring.service')->getTotalPlayersCount()['players'] }}
</span>
