<?php
namespace App\Modules\Academic\Features\AcademicAffairs;

use App\Shared\{Access, Store};

final class AcademicAffairsRepository
{
    public static function people(): array
    {
        return Store::rows("SELECT ns.id AS nhan_su_id,ns.user_id,ns.phan_loai,u.hoten,
            'active' AS status FROM nhan_su_chuyen_mon ns JOIN users u ON u.user_id=ns.user_id
            WHERE ns.deleted_at IS NULL AND ns.trang_thai='hoat_dong' AND u.is_deleted=0 ORDER BY u.hoten");
    }

    public static function registrations(int $studentId): array
    {
        return Store::rows('SELECT d.*,k.ma AS ma_khoa,k.ten AS ten_khoa FROM dang_ky_khoa_hoc d
            JOIN khoa_hoc k ON k.id=d.khoa_hoc_id WHERE d.hoc_vien_id=? ORDER BY d.id DESC', [$studentId]);
    }

    public static function summary(array $actor): array
    {
        [$scope, $params] = Access::classScope($actor);
        $leave = Store::rows("SELECT d.id,d.ly_do,l.ma_lop,u.hoten FROM don_xin_nghi d
            JOIN buoi_hoc b ON b.id=d.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id JOIN users u ON u.user_id=d.nguoi_gui_id
            WHERE d.trang_thai='cho_duyet' AND l.deleted_at IS NULL".$scope.' ORDER BY d.id DESC', $params);
        $makeups = (int)Store::one("SELECT COUNT(*) AS n FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id
            WHERE b.loai='bu' AND b.trang_thai NOT IN ('huy','doi') AND l.deleted_at IS NULL".$scope, $params)['n'];
        return ['pendingLeave'=>count($leave), 'makeups'=>$makeups, 'leave'=>array_slice($leave, 0, 5)];
    }

    public static function notifications(int $userId): array
    {
        return Store::rows('SELECT id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,doc_luc,created_at
            FROM thong_bao WHERE user_id=? ORDER BY id DESC LIMIT 100', [$userId]);
    }

    public static function makeups(array $actor): array
    {
        [$scope,$params]=Access::classScope($actor);
        return Store::rows('SELECT hb.*,hv.ho_ten AS ten_hoc_vien,b.ngay,b.gio_bat_dau,b.gio_ket_thuc,b.buoi_goc_id,
            l.ma_lop,u.hoten AS nguoi_xep FROM hoc_bu_hoc_vien hb JOIN hoc_vien hv ON hv.id=hb.hoc_vien_id
            JOIN buoi_hoc b ON b.id=hb.buoi_bu_id JOIN lop_hoc l ON l.id=b.lop_id JOIN users u ON u.user_id=hb.xep_boi
            WHERE l.deleted_at IS NULL'.$scope.' ORDER BY b.ngay DESC,hb.id DESC',$params);
    }
}
