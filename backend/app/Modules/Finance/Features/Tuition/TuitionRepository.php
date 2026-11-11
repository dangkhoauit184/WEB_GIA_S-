<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Shared\{ApiException,Store};

final class TuitionRepository
{
    private const SELECT="SELECT gd.*,hd.ma_hd,ph.hoten AS ten_phu_huynh,hv.ho_ten AS ten_hoc_vien,
        ad.hoten AS ten_admin,(SELECT MAX(sk.payload_sha256) FROM su_kien_thanh_toan sk WHERE sk.giao_dich_id=gd.id) AS webhook_hash
        FROM giao_dich_truc_tuyen gd JOIN hoa_don hd ON hd.id=gd.hoa_don_id
        JOIN users ph ON ph.user_id=gd.phu_huynh_id
        LEFT JOIN dang_ky_khoa_hoc dk ON dk.id=hd.dang_ky_khoa_hoc_id
        LEFT JOIN lop_hoc_vien lhv ON lhv.id=hd.lop_hoc_vien_id
        LEFT JOIN hoc_vien hv ON hv.id=COALESCE(dk.hoc_vien_id,lhv.hoc_vien_id)
        LEFT JOIN users ad ON ad.user_id=gd.xac_nhan_boi";

    public static function transactions(): array
    {
        return Store::rows(self::SELECT.' ORDER BY gd.id DESC LIMIT 500');
    }
    public static function find(int $id): array
    {
        return Store::one(self::SELECT.' WHERE gd.id=?',[$id])
            ?? throw new ApiException(404,'Không tìm thấy giao dịch.','PAYMENT_NOT_FOUND');
    }
}
