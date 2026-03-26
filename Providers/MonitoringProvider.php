<?php

namespace Flute\Modules\Monitoring\Providers;

use Flute\Admin\Packages\Dashboard\Services\DashboardService;
use Flute\Core\Events\ResponseEvent;
use Flute\Core\Support\ModuleServiceProvider;
use Flute\Core\Template\Template;
use Flute\Modules\Monitoring\Admin\Package\MonitoringPackage;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;
use Flute\Modules\Monitoring\Listeners\HeadersListener;
use Flute\Modules\Monitoring\Services\MonitoringDashboardService;
use Flute\Modules\Monitoring\Services\MonitoringService;

class MonitoringProvider extends ModuleServiceProvider
{
    public array $extensions = [];

    public function boot(\DI\Container $container): void
    {
        $this->bootstrapModule();

        try {
            if (!orm()->getSchema()->defines('serverStatus') && !orm()->getSchema()->defines('ServerStatus')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $this->loadViews('Resources/views', 'monitoring');

        $this->loadScss('Resources/assets/scss/monitoring.scss');

        events()->addListener(ResponseEvent::NAME, [HeadersListener::class, 'onRouteResponse']);

        $monitoringService = $container->get(MonitoringService::class);
        $monitoringService->setupCron();

        $this->loadPackage(new MonitoringPackage());

        $container->set('monitoring.service', $monitoringService);
        $container->set('monitoring.dashboard', $container->get(MonitoringDashboardService::class));

        if (is_admin_path() && config('app.cron_mode') && request()->getPathInfo() === '/admin') {
            $this->registerDashboardTabs($container);
        }

        if (!is_admin_path()) {
            $template = $container->get(Template::class);

            if (method_exists($template, 'prependTemplateToSection')) {
                $template->prependTemplateToSection('navbar-logo', 'monitoring::components.navbar-logo');
            } else {
                $template->prependToSection('navbar-logo', $template->render('monitoring::components.navbar-logo'));
            }
        }
    }

    public function register(\DI\Container $container): void
    {
    }

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
