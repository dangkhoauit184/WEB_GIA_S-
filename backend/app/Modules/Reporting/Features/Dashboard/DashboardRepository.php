<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

final class DashboardRepository
{
    public static function summary(\DateTimeImmutable $month): array
    {
        $start = $month->format('Y-m-d');
        $end = $month->modify('+1 month')->format('Y-m-d');
        $seriesStart = $month->modify('-5 months')->format('Y-m-d');

        $counts = Store::one("SELECT
            (SELECT COUNT(*) FROM hoc_vien WHERE deleted_at IS NULL AND trang_thai='dang_hoc') AS students,
            (SELECT COUNT(*) FROM khoa_hoc WHERE is_active=1) AS courses,
            (SELECT COUNT(*) FROM lop_hoc WHERE deleted_at IS NULL AND trang_thai='dang_hoc') AS classes,
            (SELECT COALESCE(SUM(GREATEST(0, phai_thu - da_thu)), 0) FROM hoa_don WHERE trang_thai IN ('chua_thanh_toan','mot_phan')) AS debt,
            (SELECT COUNT(*) FROM hoa_don WHERE trang_thai IN ('chua_thanh_toan','mot_phan') AND han_thanh_toan IS NOT NULL AND han_thanh_toan < CURRENT_DATE()) AS overdueInvoices");

        $pending = Store::one("SELECT
            (SELECT COUNT(*) FROM dang_ky_khoa_hoc WHERE trang_thai='cho_xep_lop') AS enrollments,
            (SELECT COUNT(*) FROM giao_dich_truc_tuyen WHERE trang_thai='cho_xac_nhan') AS payments,
            (SELECT COUNT(*) FROM dieu_chinh_cong WHERE trang_thai='cho_duyet') AS corrections,
            (SELECT COUNT(*) FROM yeu_cau_nghi_lop WHERE trang_thai='cho_duyet') AS classLeave");

        // 6-month confirmed net revenue series (uses idx_tt_ngay range scan; Asia/Ho_Chi_Minh session timezone)
        $revenue = Store::rows(
            "SELECT DATE_FORMAT(ngay_thanh_toan, '%Y-%m') AS month,
                    COALESCE(SUM(CASE WHEN loai='thu' THEN so_tien ELSE -so_tien END), 0) AS amount
             FROM thanh_toan
             WHERE trang_thai='hop_le' AND ngay_thanh_toan >= ? AND ngay_thanh_toan < ?
             GROUP BY DATE_FORMAT(ngay_thanh_toan, '%Y-%m')
             ORDER BY month",
            [$seriesStart.' 00:00:00', $end.' 00:00:00']
        );
        $amounts = array_column($revenue, 'amount', 'month');
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = $month->modify("-$i months")->format('Y-m');
            $series[] = ['month' => $key, 'amount' => (int)($amounts[$key] ?? 0)];
        }

        $currentKey = $month->format('Y-m');
        $prevKey = $month->modify('-1 month')->format('Y-m');
        $received = (int)($amounts[$currentKey] ?? 0);
        $previousReceived = (int)($amounts[$prevKey] ?? 0);
        $revenueChangePct = $previousReceived > 0
            ? round(($received - $previousReceived) * 100 / $previousReceived, 1)
            : null;

        // Admin-only teaching payroll summary for the selected month, following PayrollRepository::summary rules
        $yearNum = (int)$month->format('Y');
        $monthNum = (int)$month->format('m');
        $payrollRow = Store::one(
            "SELECT
                COALESCE(SUM(COALESCE(bc.thuc_nhan, w.tien, 0)), 0) AS total,
                COALESCE(SUM(CASE WHEN COALESCE(bc.trang_thai, 'mo') = 'da_tra' THEN COALESCE(bc.thuc_nhan, 0) ELSE 0 END), 0) AS paid,
                COALESCE(SUM(CASE WHEN COALESCE(bc.trang_thai, 'mo') = 'da_chot' THEN COALESCE(bc.thuc_nhan, 0) ELSE 0 END), 0) AS closed,
                COALESCE(SUM(CASE WHEN COALESCE(bc.trang_thai, 'mo') = 'mo' THEN COALESCE(bc.thuc_nhan, w.tien, 0) ELSE 0 END), 0) AS openAmount,
                COALESCE(SUM(COALESCE(bc.so_buoi, w.so_buoi, 0)), 0) AS sessions,
                COALESCE(SUM(COALESCE(bc.tong_phut, w.tong_phut, 0)), 0) AS minutes,
                COUNT(DISTINCT CASE WHEN COALESCE(bc.so_buoi, w.so_buoi, 0) > 0 OR bc.id IS NOT NULL THEN u.user_id END) AS staffCount
             FROM nhan_su_chuyen_mon ns
             JOIN users u ON u.user_id = ns.user_id
             LEFT JOIN bang_cong_thang bc ON bc.user_id = u.user_id AND bc.nam = ? AND bc.thang = ?
             LEFT JOIN (
                 SELECT pc.user_id,
                        COUNT(*) AS so_buoi,
                        COALESCE(SUM(pc.so_phut_tinh_cong), 0) AS tong_phut,
                        COALESCE(SUM(fn_tien_cong(pc.cach_tinh_luong, pc.don_gia, pc.so_phut_tinh_cong)), 0) AS tien
                 FROM phan_cong_buoi_hoc pc
                 JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
                 WHERE b.ngay >= ? AND b.ngay < ?
                   AND b.trang_thai = 'da_day'
                   AND pc.trang_thai = 'da_day'
                   AND pc.trang_thai_cong = 'hop_le'
                   AND pc.cach_tinh_luong IS NOT NULL
                   AND pc.so_phut_tinh_cong IS NOT NULL
                 GROUP BY pc.user_id
             ) w ON w.user_id = u.user_id
             WHERE ns.deleted_at IS NULL OR bc.id IS NOT NULL",
            [$yearNum, $monthNum, $start, $end]
        );

        return [
            'month' => $currentKey,
            'previousMonth' => $prevKey,
            'counts' => array_map('intval', $counts),
            'pending' => array_map('intval', $pending),
            'revenue' => $series,
            'received' => $received,
            'previousReceived' => $previousReceived,
            'revenueDelta' => $received - $previousReceived,
            'revenueChangePct' => $revenueChangePct,
            'payroll' => [
                'total' => (int)($payrollRow['total'] ?? 0),
                'paid' => (int)($payrollRow['paid'] ?? 0),
                'closed' => (int)($payrollRow['closed'] ?? 0),
                'open' => (int)($payrollRow['openAmount'] ?? 0),
                'sessions' => (int)($payrollRow['sessions'] ?? 0),
                'minutes' => (int)($payrollRow['minutes'] ?? 0),
                'staffCount' => (int)($payrollRow['staffCount'] ?? 0),
            ],
            'recent' => \App\Modules\Administration\Features\Audit\AuditRepository::all(['page' => 1, 'perPage' => 6])['items'],
        ];
    }
}
