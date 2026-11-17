<?php
namespace App\Modules\Academic\Features\Attendance;

use App\Shared\{Access, ApiException, Store};

final class AttendanceFilter
{
    public static function teacher(): array
    {
        $actor = Access::guard('VanHanhBuoiHoc');
        if ($actor['role'] !== 'GIAO_VIEN') Access::forbidden();
        $profile = Store::one("SELECT id FROM nhan_su_chuyen_mon WHERE user_id=?
            AND deleted_at IS NULL AND trang_thai='hoat_dong' AND FIND_IN_SET('giao_vien',phan_loai)", [$actor['userId']]);
        if (!$profile) throw new ApiException(403, 'Hồ sơ giáo viên chưa hoạt động.');
        $actor['teacherId'] = (int)$profile['id'];
        return $actor;
    }
}
