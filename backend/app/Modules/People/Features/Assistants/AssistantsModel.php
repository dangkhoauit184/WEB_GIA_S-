<?php
namespace App\Modules\People\Features\Assistants;

use App\Shared\ApiModel;

final class AssistantsModel
{
    private const FORBIDDEN_PATTERN = '/^(don_gia|donGia|luong|thu_lao|thuLao|muc_tra|mucTra|cach_tinh_luong|cachTinhLuong|tien|hocPhi|hoc_phi|donGiaGioChuan|don_gia_gio_chuan|hocPhiBuoiDeXuat|hoc_phi_buoi_de_xuat)/i';

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
