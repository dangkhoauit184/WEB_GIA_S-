<?php
namespace App\Modules\Academic\Features\Attendance;

final class AttendanceModel
{
    public static function map(array $row): array
    {
        return \App\Shared\ApiModel::map($row);
    }
}
