<?php
namespace App\Modules\Reporting\Features\Dashboard;

final class DashboardFilter
{
    public static function read(): array
    {
        return \App\Modules\Administration\Features\Permissions\PermissionsFilter::administer();
    }
}
