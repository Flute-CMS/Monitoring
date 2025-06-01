<?php

namespace Flute\Modules\Monitoring\Providers;

use Flute\Admin\Packages\Dashboard\Services\DashboardService;
use Flute\Modules\Monitoring\Services\MonitoringDashboardService;
use Flute\Modules\Monitoring\Services\MonitoringService;
use Flute\Core\Support\ModuleServiceProvider;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;

class MonitoringProvider extends ModuleServiceProvider
{
    public array $extensions = [];

    public function boot(\DI\Container $container): void
    {
        $this->bootstrapModule();

        if (!orm()->getRepository(ServerStatus::class)) {
            return;
        }

        $this->loadViews('Resources/views', 'monitoring');

        $this->loadScss('Resources/assets/scss/monitoring.scss');

        $monitoringService = $container->get(MonitoringService::class);
        $monitoringService->setupCron();

        $container->set('monitoring.service', $monitoringService);
        $container->set('monitoring.dashboard', $container->get(MonitoringDashboardService::class));

        if (is_admin_path() && config('app.cron_mode') && request()->getPathInfo() === '/admin') {
            $this->registerDashboardTabs($container);
        }

        if (!is_admin_path()) {
            template()->prependToSection('navbar-logo', render('monitoring::components.navbar-logo'));
        }
    }

    public function register(\DI\Container $container): void {}

    /**
     * Register monitoring dashboard tabs
     */
    protected function registerDashboardTabs(\DI\Container $container): void
    {
        /** @var MonitoringDashboardService $monitoringDashboard */
        $monitoringDashboard = $container->get(MonitoringDashboardService::class);

        /** @var DashboardService $dashboard */
        $dashboard = app()->get(DashboardService::class);

        $monitoringDashboard->registerDashboardTab($dashboard);
    }
}
