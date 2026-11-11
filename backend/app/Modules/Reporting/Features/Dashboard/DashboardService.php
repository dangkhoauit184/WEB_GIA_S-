<?php
namespace App\Modules\Reporting\Features\Dashboard;

final class DashboardService
{
    public static function summary(array $query): array
    {
        DashboardFilter::read();
        return DashboardRepository::summary(DashboardValidator::month($query));
    }
}
