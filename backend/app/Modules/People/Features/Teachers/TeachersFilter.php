<?php
namespace App\Modules\People\Features\Teachers;

final class TeachersFilter
{
    public static function selfProfile(): array
    {
        $actor = \App\Shared\Access::user();
        if (!in_array($actor['role'], ['GIAO_VIEN','GIA_SU','TRO_GIANG'], true)) \App\Shared\Access::forbidden();
        $row = \App\Shared\Store::one('SELECT id FROM nhan_su_chuyen_mon WHERE user_id=? AND deleted_at IS NULL', [$actor['userId']]);
        if (!$row) throw new \App\Shared\ApiException(404, 'Chưa có hồ sơ chuyên môn. Vui lòng liên hệ quản trị viên.');
        return $actor + ['teacherId'=>(int)$row['id']];
    }
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        return \App\Shared\Access::guard('QuanLyNhanSu', $action, $staffOnly);
    }
}
