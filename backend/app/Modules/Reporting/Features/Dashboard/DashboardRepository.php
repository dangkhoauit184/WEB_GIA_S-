<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

final class DashboardRepository
{
    public static function summary(\DateTimeImmutable $month): array
    {
        $start=$month->format('Y-m-d'); $end=$month->modify('+1 month')->format('Y-m-d');
        $counts=Store::one("SELECT
            (SELECT COUNT(*) FROM hoc_vien WHERE deleted_at IS NULL AND trang_thai='dang_hoc') AS students,
            (SELECT COUNT(*) FROM khoa_hoc WHERE is_active=1) AS courses,
            (SELECT COUNT(*) FROM lop_hoc WHERE deleted_at IS NULL AND trang_thai='dang_hoc') AS classes,
            (SELECT COALESCE(SUM(phai_thu-da_thu),0) FROM hoa_don WHERE trang_thai IN ('chua_thanh_toan','mot_phan')) AS debt");
        $pending=Store::one("SELECT
            (SELECT COUNT(*) FROM dang_ky_khoa_hoc WHERE trang_thai='cho_xep_lop') AS enrollments,
            (SELECT COUNT(*) FROM giao_dich_truc_tuyen WHERE trang_thai='cho_xac_nhan') AS payments,
            (SELECT COUNT(*) FROM dieu_chinh_cong WHERE trang_thai='cho_duyet') AS corrections,
            (SELECT COUNT(*) FROM yeu_cau_nghi_lop WHERE trang_thai='cho_duyet') AS classLeave");
        $revenue=Store::rows("SELECT DATE_FORMAT(ngay_thanh_toan,'%Y-%m') AS month,
            SUM(CASE WHEN loai='thu' THEN so_tien ELSE -so_tien END) AS amount FROM thanh_toan
            WHERE trang_thai='hop_le' AND ngay_thanh_toan>=? AND ngay_thanh_toan<?
            GROUP BY DATE_FORMAT(ngay_thanh_toan,'%Y-%m') ORDER BY month",
            [$month->modify('-3 months')->format('Y-m-d'),$end]);
        $amounts=array_column($revenue,'amount','month'); $series=[];
        for($i=3;$i>=0;$i--) {
            $key=$month->modify("-$i months")->format('Y-m');
            $series[]=['month'=>$key,'amount'=>(int)($amounts[$key]??0)];
        }
        return ['month'=>$month->format('Y-m'),'counts'=>array_map('intval',$counts),
            'pending'=>array_map('intval',$pending),'revenue'=>$series,'received'=>(int)($amounts[$month->format('Y-m')]??0),
            'recent'=>\App\Modules\Administration\Features\Audit\AuditRepository::all(['page'=>1,'perPage'=>6])['items']];
    }
}
