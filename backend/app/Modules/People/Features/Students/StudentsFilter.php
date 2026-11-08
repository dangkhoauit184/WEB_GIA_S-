<?php
namespace App\Modules\People\Features\Students;

final class StudentsFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        return \App\Shared\Access::guard('QuanLyLopHoc', $action, $staffOnly);
    }
}
