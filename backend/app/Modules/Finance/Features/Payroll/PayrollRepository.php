<?php
namespace App\Modules\Finance\Features\Payroll;

use App\Shared\{ApiException,Store};
use App\Modules\Administration\Features\Permissions\PermissionsRepository;

final class PayrollRepository
{
    public static function sessions(int $userId,\DateTimeImmutable $month): array
    {
        return Store::rows("SELECT pc.id,b.ngay,l.ma_lop,pc.checkin_luc,pc.checkout_luc,pc.so_phut_tinh_cong,
            pc.don_gia,pc.checkin_tre,pc.trang_thai_cong,pc.trang_thai,b.trang_thai AS trang_thai_buoi,
            CASE WHEN pc.trang_thai_cong='hop_le' AND pc.trang_thai='da_day' AND b.trang_thai='da_day'
            THEN fn_tien_cong(pc.cach_tinh_luong,pc.don_gia,pc.so_phut_tinh_cong) ELSE 0 END AS tien
            FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id
            WHERE pc.user_id=? AND b.ngay>=? AND b.ngay<? AND pc.trang_thai<>'da_go' ORDER BY b.ngay,b.gio_bat_dau,pc.id",
            [$userId,$month->format('Y-m-d'),$month->modify('+1 month')->format('Y-m-d')]);
    }
    public static function summary(\DateTimeImmutable $month): array
    {
        $start=$month->format('Y-m-d'); $end=$month->modify('+1 month')->format('Y-m-d');
        $rows=Store::rows("SELECT ns.id AS nhan_su_id,u.user_id,u.hoten,ns.phan_loai,ns.don_gia_gio_chuan,
            bc.id,COALESCE(bc.trang_thai,'mo') AS trang_thai,bc.thang,bc.nam,bc.tra_boi,bc.tra_luc,bc.ma_giao_dich_chi,
            COALESCE(bc.so_buoi,w.so_buoi,0) AS so_buoi,COALESCE(bc.tong_phut,w.tong_phut,0) AS tong_phut,
            COALESCE(bc.thuc_nhan,w.tien,0) AS thuc_nhan,COALESCE(bc.so_lan_tre,w.tre,0) AS so_lan_tre,
            COALESCE(bc.thuong,0) AS thuong,COALESCE(bc.khau_tru,0) AS khau_tru
            FROM nhan_su_chuyen_mon ns JOIN users u ON u.user_id=ns.user_id
            LEFT JOIN bang_cong_thang bc ON bc.user_id=u.user_id AND bc.nam=? AND bc.thang=?
            LEFT JOIN (SELECT pc.user_id,COUNT(*) AS so_buoi,SUM(pc.so_phut_tinh_cong) AS tong_phut,
                SUM(fn_tien_cong(pc.cach_tinh_luong,pc.don_gia,pc.so_phut_tinh_cong)) AS tien,SUM(pc.checkin_tre) AS tre
                FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
                WHERE b.ngay>=? AND b.ngay<? AND b.trang_thai='da_day' AND pc.trang_thai='da_day'
                AND pc.trang_thai_cong='hop_le' GROUP BY pc.user_id) w ON w.user_id=u.user_id
            WHERE ns.deleted_at IS NULL OR bc.id IS NOT NULL ORDER BY u.hoten,u.user_id",
            [(int)$month->format('Y'),(int)$month->format('m'),$start,$end]);
        $history=Store::rows('SELECT h.*,u.hoten,ad.hoten AS ten_admin FROM lich_su_don_gia_gio h
            JOIN nhan_su_chuyen_mon ns ON ns.id=h.nhan_su_id JOIN users u ON u.user_id=ns.user_id
            JOIN users ad ON ad.user_id=h.nguoi_dat_id ORDER BY h.hieu_luc_tu DESC,h.id DESC LIMIT 500');
        return ['month'=>$month->format('Y-m'),'items'=>$rows,'history'=>$history];
    }

    public static function pay(int $id,?string $reference,array $actor): array
    {
        return Store::transaction(function() use($id,$reference,$actor) {
            PermissionsRepository::lockAdministrator($actor);
            $owner=Store::one('SELECT user_id FROM bang_cong_thang WHERE id=?',[$id]);
            if(!$owner) throw new ApiException(404,'Không tìm thấy bảng công.','PAYROLL_NOT_FOUND');
            Store::one('SELECT user_id FROM users WHERE user_id=? FOR UPDATE',[$owner['user_id']]);
            $before=Store::one('SELECT * FROM bang_cong_thang WHERE id=? FOR UPDATE',[$id]);
            if($before['trang_thai']==='da_tra') return $before;
            if($before['trang_thai']!=='da_chot') throw new ApiException(409,'Cần chốt công trước khi xác nhận chi trả.','PAYROLL_NOT_CLOSED');
            Store::execute("UPDATE bang_cong_thang SET trang_thai='da_tra',tra_boi=?,tra_luc=NOW(),
                ngay_tra=CURRENT_DATE(),ma_giao_dich_chi=? WHERE id=?",[$actor['userId'],$reference,$id]);
            $after=Store::one('SELECT * FROM bang_cong_thang WHERE id=?',[$id]);
            Store::recordAudit((int)$actor['userId'],'sua','bang_cong_thang',$id,$before,$after);
            return $after;
        });
    }
}
