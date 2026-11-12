<?php
namespace App\Modules\Finance\Features\Tuition;

final class TuitionFilter
{
    public static function check(string $action = 'view'): array
    {
        return \App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check('HocPhiThanhToan', $action);
    }
}
