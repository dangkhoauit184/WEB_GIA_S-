<?php
namespace App\Modules\People\Features\Parents;

final class ParentsFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        $actor = \App\Shared\Access::guard('TiepNhanYeuCau', $action, $staffOnly);
        if ($actor['role'] === 'GIAO_VU') return \App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check('TiepNhanYeuCau', $action);
        return $actor;
    }
}
