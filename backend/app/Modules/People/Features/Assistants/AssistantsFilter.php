<?php
namespace App\Modules\People\Features\Assistants;

use App\Shared\{Access, ApiException, Store};

final class AssistantsFilter
{
    public static function assistant(): array
    {
        $actor = Access::guard('VanHanhBuoiHoc');
        if ($actor['role'] !== 'TRO_GIANG') {
            Access::forbidden();
        }
        $profile = Store::one("SELECT id, ma_ns, trang_thai FROM nhan_su_chuyen_mon
            WHERE user_id=? AND deleted_at IS NULL AND trang_thai='hoat_dong' AND FIND_IN_SET('tro_giang', phan_loai)",
            [$actor['userId']]);
        if (!$profile) {
            throw new ApiException(403, 'Hồ sơ trợ giảng chưa hoạt động hoặc không hợp lệ.');
        }
        $actor['assistantId'] = (int)$profile['id'];
        return $actor;
    }
}
