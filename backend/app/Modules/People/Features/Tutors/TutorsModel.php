<?php
namespace App\Modules\People\Features\Tutors;

final class TutorsModel
{
    private const FORBIDDEN_PATTERN = '/(don_?gia|luong|thu_?lao|muc_?tra|cach_?tinh_?luong|tien|hoc_?phi|ngan_?hang|so_?tai_?khoan|chu_?tai_?khoan)/i';

    public static function map(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (is_string($key) && preg_match(self::FORBIDDEN_PATTERN, $key)) {
                continue;
            }
            $camel = is_string($key)
                ? preg_replace_callback('/_([a-z])/', static fn($m) => strtoupper($m[1]), $key)
                : $key;
            $out[$camel] = is_array($value) ? self::map($value) : $value;
        }
        return $out;
    }
}
