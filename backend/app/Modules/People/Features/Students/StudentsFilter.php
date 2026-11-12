<?php
namespace App\Modules\People\Features\Students;

final class StudentsFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        $actor = \App\Shared\Access::guard('QuanLyLopHoc', $action, $staffOnly);
        if ($actor['role'] === 'GIAO_VU') return \App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check('QuanLyLopHoc', $action);
        return $actor;
    }
}
