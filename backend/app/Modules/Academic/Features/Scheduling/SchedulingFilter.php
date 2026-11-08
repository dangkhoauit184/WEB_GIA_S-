<?php
namespace App\Modules\Academic\Features\Scheduling;

final class SchedulingFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        return \App\Shared\Access::guard('VanHanhBuoiHoc', $action, $staffOnly);
    }
}
