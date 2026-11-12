<?php
namespace App\Modules\Academic\Features\Scheduling;

final class SchedulingFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        $actor = \App\Shared\Access::guard('VanHanhBuoiHoc', $action, $staffOnly);
        if ($actor['role'] === 'GIAO_VU') return \App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check('VanHanhBuoiHoc', $action);
        return $actor;
    }
}
