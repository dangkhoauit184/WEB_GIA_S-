<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

/**
 * 5. Báo cáo Công nợ tồn & Quá hạn (Outstanding & Overdue Debt Report — Admin only).
 */
final class DebtReportQuery
{
    public static function run(array $filters, array $actor): array
    {
        $campusId = ReportSqlHelper::enforceScope($filters, $actor);
        $relJoins = ReportSqlHelper::invoiceRelationJoins();

        $agingExpr = "CASE
            WHEN hd.han_thanh_toan IS NOT NULL AND hd.han_thanh_toan < CURRENT_DATE() THEN 'qua_han'
            WHEN hd.han_thanh_toan IS NOT NULL THEN 'chua_den_han'
            ELSE 'khong_co_han'
        END";

        $where = ["hd.trang_thai IN ('chua_thanh_toan', 'mot_phan')", '(hd.phai_thu - hd.da_thu) > 0'];
        $params = [];

        if ($campusId !== null) {
            $where[] = 'COALESCE(l_dk.co_so_id, l_leg.co_so_id) = ?';
            $params[] = $campusId;
        }
        if (!empty($filters['khoaHocId'])) {
            $where[] = 'COALESCE(dk.khoa_hoc_id, l_leg.khoa_hoc_id) = ?';
            $params[] = (int)$filters['khoaHocId'];
        }
        if (!empty($filters['lopId'])) {
            $where[] = 'COALESCE(l_dk.id, l_leg.id) = ?';
            $params[] = (int)$filters['lopId'];
        }
        if (!empty($filters['trangThai'])) {
            if (in_array($filters['trangThai'], ['qua_han', 'chua_den_han', 'khong_co_han'], true)) {
                $where[] = "($agingExpr) = ?";
            } else {
                $where[] = 'hd.trang_thai = ?';
            }
            $params[] = $filters['trangThai'];
        }
        $whereSql = implode(' AND ', $where);

        $byGroupRaw = Store::rows(
            "SELECT
                COALESCE(k.id, 0) AS khoaHocId,
                COALESCE(k.ma, 'CHUA_GAN') AS maKhoa,
                COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                COALESCE(cs.id, 0) AS coSoId,
                COALESCE(cs.ten_co_so, 'Chưa xếp cơ sở / Trực tuyến') AS tenCoSo,
                COUNT(hd.id) AS soHoaDonNo,
                COALESCE(SUM(hd.phai_thu), 0) AS tongPhaiThu,
                COALESCE(SUM(hd.da_thu), 0) AS tongDaThu,
                COALESCE(SUM(hd.phai_thu - hd.da_thu), 0) AS tongConNo,
                SUM(CASE WHEN ($agingExpr) = 'qua_han' THEN 1 ELSE 0 END) AS soQuaHan,
                COALESCE(SUM(CASE WHEN ($agingExpr) = 'qua_han' THEN (hd.phai_thu - hd.da_thu) ELSE 0 END), 0) AS tienQuaHan,
                SUM(CASE WHEN ($agingExpr) = 'chua_den_han' THEN 1 ELSE 0 END) AS soChuaDenHan,
                COALESCE(SUM(CASE WHEN ($agingExpr) = 'chua_den_han' THEN (hd.phai_thu - hd.da_thu) ELSE 0 END), 0) AS tienChuaDenHan,
                SUM(CASE WHEN ($agingExpr) = 'khong_co_han' THEN 1 ELSE 0 END) AS soKhongCoHan,
                COALESCE(SUM(CASE WHEN ($agingExpr) = 'khong_co_han' THEN (hd.phai_thu - hd.da_thu) ELSE 0 END), 0) AS tienKhongCoHan
             FROM hoa_don hd
             $relJoins
             WHERE $whereSql
             GROUP BY khoaHocId, maKhoa, tenKhoa, coSoId, tenCoSo
             ORDER BY tienQuaHan DESC, tongConNo DESC",
            $params
        );

        $byGroup = [];
        $summary = [
            'soHoaDonNo' => 0, 'tongPhaiThu' => 0, 'tongDaThu' => 0, 'tongConNo' => 0,
            'soHoaDonQuaHan' => 0, 'tienQuaHan' => 0,
            'soHoaDonChuaDenHan' => 0, 'tienChuaDenHan' => 0,
            'soHoaDonKhongCoHan' => 0, 'tienKhongCoHan' => 0,
        ];
        foreach ($byGroupRaw as $r) {
            $item = [
                'khoaHocId' => (int)$r['khoaHocId'],
                'maKhoa' => $r['maKhoa'],
                'tenKhoa' => $r['tenKhoa'],
                'coSoId' => (int)$r['coSoId'],
                'tenCoSo' => $r['tenCoSo'],
                'soHoaDonNo' => (int)$r['soHoaDonNo'],
                'tongPhaiThu' => (int)$r['tongPhaiThu'],
                'tongDaThu' => (int)$r['tongDaThu'],
                'tongConNo' => (int)$r['tongConNo'],
                'soQuaHan' => (int)$r['soQuaHan'],
                'tienQuaHan' => (int)$r['tienQuaHan'],
                'soChuaDenHan' => (int)$r['soChuaDenHan'],
                'tienChuaDenHan' => (int)$r['tienChuaDenHan'],
                'soKhongCoHan' => (int)$r['soKhongCoHan'],
                'tienKhongCoHan' => (int)$r['tienKhongCoHan'],
            ];
            $byGroup[] = $item;
            $summary['soHoaDonNo'] += $item['soHoaDonNo'];
            $summary['tongPhaiThu'] += $item['tongPhaiThu'];
            $summary['tongDaThu'] += $item['tongDaThu'];
            $summary['tongConNo'] += $item['tongConNo'];
            $summary['soHoaDonQuaHan'] += $item['soQuaHan'];
            $summary['tienQuaHan'] += $item['tienQuaHan'];
            $summary['soHoaDonChuaDenHan'] += $item['soChuaDenHan'];
            $summary['tienChuaDenHan'] += $item['tienChuaDenHan'];
            $summary['soHoaDonKhongCoHan'] += $item['soKhongCoHan'];
            $summary['tienKhongCoHan'] += $item['tienKhongCoHan'];
        }

        $details = Store::rows(
            "SELECT hd.id AS hoaDonId, hd.ma_hd AS maHd,
                    DATE(COALESCE(hd.phat_hanh_luc, hd.created_at)) AS ngayPhatHanh,
                    hd.han_thanh_toan AS hanThanhToan,
                    CASE WHEN hd.han_thanh_toan IS NOT NULL AND hd.han_thanh_toan < CURRENT_DATE()
                         THEN DATEDIFF(CURRENT_DATE(), hd.han_thanh_toan) ELSE 0 END AS soNgayQuaHan,
                    ($agingExpr) AS nhomHan,
                    hd.trang_thai AS trangThai,
                    hd.phai_thu AS phaiThu, hd.da_thu AS daThu, (hd.phai_thu - hd.da_thu) AS conNo,
                    COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                    COALESCE(cs.ten_co_so, 'Chưa xếp cơ sở / Trực tuyến') AS tenCoSo,
                    COALESCE(l_dk.ma_lop, l_leg.ma_lop, 'Chưa xếp lớp') AS maLop,
                    COALESCE(hv.ma_hv, '—') AS maHv,
                    COALESCE(hv.ho_ten, '—') AS tenHocVien,
                    COALESCE(u_ph.hoten, '—') AS tenPhuHuynh,
                    COALESCE(u_ph.sdt, '') AS sdtPhuHuynh
             FROM hoa_don hd
             $relJoins
             LEFT JOIN users u_ph ON u_ph.user_id = hv.phu_huynh_user_id
             WHERE $whereSql
             ORDER BY CASE WHEN hd.han_thanh_toan IS NULL THEN 1 ELSE 0 END ASC,
                      hd.han_thanh_toan ASC,
                      (hd.phai_thu - hd.da_thu) DESC
             LIMIT ".ReportSqlHelper::DETAIL_LIMIT,
            $params
        );

        return [
            'report' => 'debt',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byGroup' => $byGroup,
            'details' => $details,
            'detailLimit' => ReportSqlHelper::DETAIL_LIMIT,
            'congThuc' => [
                'Liệt kê toàn bộ hóa đơn có trang_thai thuộc (chua_thanh_toan, mot_phan) và còn số dư phai_thu − da_thu > 0, kể cả hóa đơn phát hành trước kỳ lọc.',
                'Phân nhóm theo han_thanh_toan so với ngày hiện tại (Asia/Ho_Chi_Minh): Quá hạn (han_thanh_toan < hôm nay), Chưa đến hạn (han_thanh_toan ≥ hôm nay), Chưa đặt hạn (han_thanh_toan NULL).',
                'Sắp xếp ưu tiên hóa đơn có hạn thanh toán sớm nhất / quá hạn lâu nhất lên đầu để hỗ trợ đôn đốc thu phí.',
            ],
        ];
    }
}
