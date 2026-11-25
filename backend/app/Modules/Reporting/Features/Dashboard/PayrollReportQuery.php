<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

/**
 * 4. Báo cáo Công dạy & Quỹ lương (Teaching Hours & Payroll Report — Admin only).
 */
final class PayrollReportQuery
{
    public static function run(array $filters, array $actor): array
    {
        $campusId = ReportSqlHelper::enforceScope($filters, $actor);
        [$fromTs, $toNextTs] = ReportSqlHelper::dateBounds($filters['tuNgay'], $filters['denNgay']);

        $roleExpr = "CASE
            WHEN pc.vai_tro = 'tro_giang' THEN 'tro_giang'
            WHEN FIND_IN_SET('giao_vien', COALESCE(ns.phan_loai, '')) > 0
                 AND (l.loai_lop = 'nhom' OR FIND_IN_SET('gia_su', COALESCE(ns.phan_loai, '')) = 0) THEN 'giao_vien'
            WHEN FIND_IN_SET('gia_su', COALESCE(ns.phan_loai, '')) > 0 THEN 'gia_su'
            ELSE 'giao_vien'
        END";
        $periodStatusExpr = "COALESCE(bc.trang_thai, bc_m.trang_thai, 'mo')";
        $lateMinExpr = "CASE
            WHEN pc.checkin_tre = 1 AND pc.checkin_luc IS NOT NULL AND pc.gio_lich_bat_dau IS NOT NULL AND pc.checkin_luc > pc.gio_lich_bat_dau
            THEN TIMESTAMPDIFF(MINUTE, pc.gio_lich_bat_dau, pc.checkin_luc)
            ELSE 0
        END";
        $wageExpr = "fn_tien_cong(pc.cach_tinh_luong, pc.don_gia, pc.so_phut_tinh_cong)";

        $where = [
            'l.deleted_at IS NULL',
            'b.ngay BETWEEN ? AND ?',
            "b.trang_thai = 'da_day'",
            "pc.trang_thai = 'da_day'",
            "pc.trang_thai_cong = 'hop_le'",
            'pc.cach_tinh_luong IS NOT NULL',
            'pc.so_phut_tinh_cong IS NOT NULL',
        ];
        $params = [$filters['tuNgay'], $filters['denNgay']];

        if ($campusId !== null) {
            $where[] = 'COALESCE(b.co_so_id, l.co_so_id) = ?';
            $params[] = $campusId;
        }
        if (!empty($filters['khoaHocId'])) {
            $where[] = 'l.khoa_hoc_id = ?';
            $params[] = (int)$filters['khoaHocId'];
        }
        if (!empty($filters['lopId'])) {
            $where[] = 'l.id = ?';
            $params[] = (int)$filters['lopId'];
        }
        if (!empty($filters['nhomNhanSu'])) {
            $where[] = "($roleExpr) = ?";
            $params[] = $filters['nhomNhanSu'];
        }
        if (!empty($filters['trangThai'])) {
            $where[] = "($periodStatusExpr) = ?";
            $params[] = $filters['trangThai'];
        }
        $whereSql = implode(' AND ', $where);

        $baseFrom = "FROM phan_cong_buoi_hoc pc
            JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
            JOIN lop_hoc l ON l.id = b.lop_id
            JOIN users u ON u.user_id = pc.user_id
            LEFT JOIN nhan_su_chuyen_mon ns ON ns.user_id = pc.user_id
            LEFT JOIN co_so cs ON cs.id = COALESCE(b.co_so_id, l.co_so_id)
            LEFT JOIN khoa_hoc k ON k.id = l.khoa_hoc_id
            LEFT JOIN bang_cong_thang bc ON bc.id = pc.bang_cong_id
            LEFT JOIN bang_cong_thang bc_m ON pc.bang_cong_id IS NULL
                 AND bc_m.user_id = pc.user_id AND bc_m.nam = YEAR(b.ngay) AND bc_m.thang = MONTH(b.ngay)";

        $personRows = Store::rows(
            "SELECT
                u.user_id AS userId,
                COALESCE(ns.ma_ns, CONCAT('U-', u.user_id)) AS maNs,
                u.hoten AS hoTen,
                ($roleExpr) AS nhomNhanSu,
                COALESCE(ns.don_gia_gio_chuan, 0) AS donGiaHienTai,
                COUNT(pc.id) AS soCa,
                COALESCE(SUM(pc.so_phut_tinh_cong), 0) AS tongPhut,
                COALESCE(SUM(CASE WHEN pc.checkin_tre = 1 THEN 1 ELSE 0 END), 0) AS soLanTre,
                COALESCE(SUM($lateMinExpr), 0) AS soPhutTre,
                COALESCE(SUM($wageExpr), 0) AS tienCongCa,
                COALESCE(SUM(CASE WHEN ($periodStatusExpr) = 'mo' THEN $wageExpr ELSE 0 END), 0) AS tienMo,
                COALESCE(SUM(CASE WHEN ($periodStatusExpr) = 'da_chot' THEN $wageExpr ELSE 0 END), 0) AS tienDaChot,
                COALESCE(SUM(CASE WHEN ($periodStatusExpr) = 'da_tra' THEN $wageExpr ELSE 0 END), 0) AS tienDaTra,
                COALESCE(SUM(CASE WHEN ($periodStatusExpr) = 'mo' THEN 1 ELSE 0 END), 0) AS caMo,
                COALESCE(SUM(CASE WHEN ($periodStatusExpr) = 'da_chot' THEN 1 ELSE 0 END), 0) AS caDaChot,
                COALESCE(SUM(CASE WHEN ($periodStatusExpr) = 'da_tra' THEN 1 ELSE 0 END), 0) AS caDaTra
             $baseFrom
             WHERE $whereSql
             GROUP BY u.user_id, maNs, u.hoten, nhomNhanSu, donGiaHienTai
             ORDER BY tienCongCa DESC, u.hoten ASC",
            $params
        );

        $byRoleMap = [
            'giao_vien' => ['nhom' => 'giao_vien', 'tenNhom' => 'Giáo viên', 'soNhanSu' => 0, 'soCa' => 0, 'tongPhut' => 0, 'tongGio' => 0.0, 'soLanTre' => 0, 'soPhutTre' => 0, 'tienCongCa' => 0],
            'gia_su' => ['nhom' => 'gia_su', 'tenNhom' => 'Gia sư', 'soNhanSu' => 0, 'soCa' => 0, 'tongPhut' => 0, 'tongGio' => 0.0, 'soLanTre' => 0, 'soPhutTre' => 0, 'tienCongCa' => 0],
            'tro_giang' => ['nhom' => 'tro_giang', 'tenNhom' => 'Trợ giảng', 'soNhanSu' => 0, 'soCa' => 0, 'tongPhut' => 0, 'tongGio' => 0.0, 'soLanTre' => 0, 'soPhutTre' => 0, 'tienCongCa' => 0],
        ];
        $roleUserSets = ['giao_vien' => [], 'gia_su' => [], 'tro_giang' => []];
        $allUsers = [];

        $byPerson = [];
        $totCa = 0;
        $totPhut = 0;
        $totLanTre = 0;
        $totPhutTre = 0;
        $totTien = 0;
        $totMo = 0;
        $totChot = 0;
        $totTra = 0;

        foreach ($personRows as $r) {
            $uid = (int)$r['userId'];
            $grp = $r['nhomNhanSu'];
            $phut = (int)$r['tongPhut'];
            $ca = (int)$r['soCa'];
            $lanTre = (int)$r['soLanTre'];
            $phutTre = (int)$r['soPhutTre'];
            $tien = (int)$r['tienCongCa'];
            $mo = (int)$r['tienMo'];
            $chot = (int)$r['tienDaChot'];
            $tra = (int)$r['tienDaTra'];

            $allUsers[$uid] = true;
            $totCa += $ca;
            $totPhut += $phut;
            $totLanTre += $lanTre;
            $totPhutTre += $phutTre;
            $totTien += $tien;
            $totMo += $mo;
            $totChot += $chot;
            $totTra += $tra;

            if (isset($byRoleMap[$grp])) {
                $roleUserSets[$grp][$uid] = true;
                $byRoleMap[$grp]['soCa'] += $ca;
                $byRoleMap[$grp]['tongPhut'] += $phut;
                $byRoleMap[$grp]['soLanTre'] += $lanTre;
                $byRoleMap[$grp]['soPhutTre'] += $phutTre;
                $byRoleMap[$grp]['tienCongCa'] += $tien;
            }

            $caMo = (int)$r['caMo'];
            $caChot = (int)$r['caDaChot'];
            $caTra = (int)$r['caDaTra'];
            $statuses = [];
            if ($caMo > 0) $statuses[] = 'mo';
            if ($caChot > 0) $statuses[] = 'da_chot';
            if ($caTra > 0) $statuses[] = 'da_tra';
            $periodTag = count($statuses) === 1 ? $statuses[0] : (count($statuses) > 1 ? 'hon_hop' : 'mo');

            $byPerson[] = [
                'userId' => $uid,
                'maNs' => $r['maNs'],
                'hoTen' => $r['hoTen'],
                'nhomNhanSu' => $grp,
                'donGiaHienTai' => (int)$r['donGiaHienTai'],
                'soCa' => $ca,
                'tongPhut' => $phut,
                'tongGio' => round($phut / 60, 2),
                'soLanTre' => $lanTre,
                'soPhutTre' => $phutTre,
                'tienCongCa' => $tien,
                'tienMo' => $mo,
                'tienDaChot' => $chot,
                'tienDaTra' => $tra,
                'trangThaiKy' => $periodTag,
            ];
        }
        foreach ($byRoleMap as $k => &$rv) {
            $rv['soNhanSu'] = count($roleUserSets[$k]);
            $rv['tongGio'] = round($rv['tongPhut'] / 60, 2);
        }
        unset($rv);

        $relJoins = ReportSqlHelper::invoiceRelationJoins();
        $revWhere = ["t.trang_thai = 'hop_le'", 't.ngay_thanh_toan >= ?', 't.ngay_thanh_toan < ?'];
        $revParams = [$fromTs, $toNextTs];
        if ($campusId !== null) {
            $revWhere[] = 'COALESCE(l_dk.co_so_id, l_leg.co_so_id) = ?';
            $revParams[] = $campusId;
        }
        if (!empty($filters['khoaHocId'])) {
            $revWhere[] = 'COALESCE(dk.khoa_hoc_id, l_leg.khoa_hoc_id) = ?';
            $revParams[] = (int)$filters['khoaHocId'];
        }
        if (!empty($filters['lopId'])) {
            $revWhere[] = 'COALESCE(l_dk.id, l_leg.id) = ?';
            $revParams[] = (int)$filters['lopId'];
        }
        $revSum = Store::one(
            "SELECT COALESCE(SUM(CASE WHEN t.loai = 'thu' THEN t.so_tien ELSE -t.so_tien END), 0) AS thucThuRong
             FROM thanh_toan t
             JOIN hoa_don hd ON hd.id = t.hoa_don_id
             $relJoins
             WHERE ".implode(' AND ', $revWhere),
            $revParams
        );
        $thucThuRong = (int)($revSum['thucThuRong'] ?? 0);

        $details = Store::rows(
            "SELECT
                pc.id AS phanCongId, b.id AS buoiHocId, b.ngay,
                b.gio_bat_dau AS gioBatDau, b.gio_ket_thuc AS gioKetThuc,
                l.ma_lop AS maLop, l.ten_lop AS tenLop,
                COALESCE(cs.ten_co_so, IF(l.hinh_thuc='online','Trực tuyến','Chưa gắn cơ sở')) AS tenCoSo,
                u.hoten AS hoTen, COALESCE(ns.ma_ns, CONCAT('U-', u.user_id)) AS maNs,
                ($roleExpr) AS nhomNhanSu, pc.vai_tro AS vaiTro,
                pc.checkin_luc AS checkinLuc, pc.checkout_luc AS checkoutLuc,
                pc.so_phut_tinh_cong AS soPhutTinhCong,
                pc.checkin_tre AS checkinTre, ($lateMinExpr) AS soPhutTre,
                pc.cach_tinh_luong AS cachTinhLuong, pc.don_gia AS donGia,
                ($wageExpr) AS tienCong,
                COALESCE(bc.id, bc_m.id) AS bangCongId,
                ($periodStatusExpr) AS trangThaiKy
             $baseFrom
             WHERE $whereSql
             ORDER BY b.ngay DESC, b.gio_bat_dau DESC, u.hoten ASC
             LIMIT ".ReportSqlHelper::DETAIL_LIMIT,
            $params
        );

        $isSubFiltered = !empty($filters['nhomNhanSu']) || !empty($filters['trangThai']);

        return [
            'report' => 'payroll',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => [
                'soNhanSu' => count($allUsers),
                'soCa' => $totCa,
                'tongPhut' => $totPhut,
                'tongGio' => round($totPhut / 60, 2),
                'soLanTre' => $totLanTre,
                'soPhutTre' => $totPhutTre,
                'tongTienCongCa' => $totTien,
                'tienMo' => $totMo,
                'tienDaChot' => $totChot,
                'tienDaTra' => $totTra,
                'thucThuRong' => $thucThuRong,
                'chenhLechThuLuong' => $thucThuRong - $totTien,
                'doiChieuKhongLocVaiTro' => $isSubFiltered,
            ],
            'byRole' => array_values($byRoleMap),
            'byPerson' => $byPerson,
            'details' => $details,
            'detailLimit' => ReportSqlHelper::DETAIL_LIMIT,
            'congThuc' => [
                'Chỉ tính các ca đã hoàn tất hợp lệ: buoi_hoc.trang_thai = da_day, phan_cong_buoi_hoc.trang_thai = da_day, trang_thai_cong = hop_le.',
                'Phút tính công lấy trực tiếp so_phut_tinh_cong (giao giữa khoảng check-in/out và khung giờ lịch, không cộng giờ ngoài lịch). Số phút đi trễ tính khi checkin_tre = 1 theo chênh lệch checkin_luc − gio_lich_bat_dau.',
                'Tiền công mỗi ca dùng fn_tien_cong(cach_tinh_luong, don_gia, so_phut_tinh_cong) theo đơn giá snapshot gắn trên từng ca đã bắt đầu; không làm thay đổi kỳ đã chốt/đã trả.',
                'Đối chiếu Thực thu ròng − Quỹ lương giảng dạy chỉ so sánh học phí thực thu (phiếu hop_le cùng khoảng ngày/cơ sở/khóa/lớp) với tiền công giảng dạy, chưa tính thưởng/khấu trừ tháng hoặc chi phí vận hành khác — không phải lợi nhuận ròng toàn trung tâm.',
            ],
        ];
    }
}
