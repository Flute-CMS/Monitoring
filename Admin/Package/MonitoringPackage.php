<?php

namespace Flute\Modules\Monitoring\Admin\Package;

use Flute\Admin\Support\AbstractAdminPackage;

class MonitoringPackage extends AbstractAdminPackage
{
    public function initialize(): void
    {
        parent::initialize();

        $this->loadRoutesFromFile('routes.php');
    }

    public function getPermissions(): array
    {
        return ['admin.servers'];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'title' => __('monitoring.admin.title'),
                'icon' => 'ph.bold.hard-drives-bold',
                'url' => url('/admin/monitoring'),
                'permission' => ['admin.servers'],
                'permission_mode' => 'any',
            ],
        ];
    }

    public function getPriority(): int
    {
        return 50;
    }
}
