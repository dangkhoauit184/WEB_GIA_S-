<?php
namespace App\Modules\People\Features\Assistants;

use App\Shared\ApiModel;

final class AssistantsModel
{
    private const FORBIDDEN_PATTERN = '/^(don_?gia|luong|thu_?lao|muc_?tra|cach_?tinh_?luong|tien|hoc_?phi|ngan_?hang|so_?tai_?khoan|chu_?tai_?khoan)/i';

    public static function map(mixed $data): mixed
    {
        if (is_array($data)) {
            $mapped = ApiModel::map($data);
            return self::stripMoney($mapped);
        }
        return $data;
    }

    public static function stripMoney(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $clean = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && preg_match(self::FORBIDDEN_PATTERN, $k)) {
                continue;
            }
            $clean[$k] = is_array($v) ? self::stripMoney($v) : $v;
        }
        return $clean;
    }
}
