<span class="navbar__logo-monitoring" data-tooltip="{{ __('monitoring.navbar.logo') }}">
    <span class="navbar__logo-monitoring-indicator"></span>
    <span class="navbar__logo-monitoring-count">
        {{ app('monitoring.service')->getTotalPlayersCount()['players'] }}
    </span>
</span>

<script>
    document.addEventListener('htmx:afterSwap', function(evt) {
        const monitoringCount = document.querySelector('.navbar__logo-monitoring-count');
        const count = evt.detail.xhr.getResponseHeader('Monitoring-count');

        if (typeof count === 'string' && count !== '') {
            monitoringCount.textContent = count;
        }
    });
</script>
