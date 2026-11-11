<?php
namespace App\Modules\Academic\Features\Classes;

final class ClassesFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        $user=\App\Shared\Access::guard('QuanLyLopHoc', $action, $staffOnly);
        if($staffOnly && $action!=='view' && $user['role']!=='ADMIN') \App\Shared\Access::forbidden();
        return $user;
    }
}
