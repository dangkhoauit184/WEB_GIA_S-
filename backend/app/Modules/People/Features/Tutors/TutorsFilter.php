<?php
namespace App\Modules\People\Features\Tutors;

use App\Shared\{Access, ApiException, Store};

final class TutorsFilter
{
    public static function tutor(): array
    {
        $actor = Access::guard('VanHanhBuoiHoc');
        if ($actor['role'] !== 'GIA_SU') Access::forbidden();
        $profile = Store::one("SELECT id FROM nhan_su_chuyen_mon WHERE user_id=?
            AND deleted_at IS NULL AND trang_thai='hoat_dong' AND FIND_IN_SET('gia_su', phan_loai)", [$actor['userId']]);
        if (!$profile) {
            throw new ApiException(403, 'Hồ sơ gia sư chưa hoạt động.');
        }
        $actor['tutorId'] = (int)$profile['id'];
        $actor['teacherId'] = (int)$profile['id'];
        return $actor;
    }
}
