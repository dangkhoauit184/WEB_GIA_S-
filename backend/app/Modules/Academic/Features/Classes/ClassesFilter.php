<?php
namespace App\Modules\Academic\Features\Classes;

final class ClassesFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        return \App\Shared\Access::guard('QuanLyLopHoc', $action, $staffOnly);
    }
}
