<?php
namespace App\Modules\People\Features\Teachers;

final class TeachersFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        return \App\Shared\Access::guard('QuanLyNhanSu', $action, $staffOnly);
    }
}
