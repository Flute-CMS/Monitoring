<?php

use Flute\Core\Router\Router;
use Flute\Modules\Monitoring\Admin\Package\Screens\MonitoringSettingsScreen;
use Flute\Modules\Monitoring\Admin\Package\Screens\MonitoringTestScreen;

Router::screen('/admin/monitoring', MonitoringSettingsScreen::class);
Router::screen('/admin/monitoring/test', MonitoringTestScreen::class);
Router::screen('/admin/monitoring/test/{id}', MonitoringTestScreen::class);
