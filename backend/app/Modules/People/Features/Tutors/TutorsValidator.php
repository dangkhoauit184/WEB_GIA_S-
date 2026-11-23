<?php
namespace App\Modules\People\Features\Tutors;

use App\Modules\Academic\Features\Attendance\AttendanceValidator;
use App\Modules\People\Features\Teachers\TeachersValidator;

final class TutorsValidator
{
    public static function fields(array $body, array $allowed): void
    {
        AttendanceValidator::fields($body, $allowed);
    }

    public static function text(mixed $value, int $max, bool $required = false): ?string
    {
        return AttendanceValidator::text($value, $max, $required);
    }

    public static function dateTime(mixed $value, string $label = 'Thời gian'): ?string
    {
        return AttendanceValidator::dateTime($value, $label);
    }

    public static function log(array $body): array
    {
        return AttendanceValidator::log($body);
    }

    public static function availability(array $body): array
    {
        AttendanceValidator::fields($body, ['thu', 'gioBatDau', 'gioKetThuc']);
        return TeachersValidator::validate(['lichRanhs' => [$body]], false)['lichRanhs'][0];
    }
}
