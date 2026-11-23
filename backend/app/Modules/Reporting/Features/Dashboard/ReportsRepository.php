<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

final class ReportsRepository
{
    private const DETAIL_LIMIT = 5000;

    private static function effectiveCampus(array $filters, array $actor): ?int
    {
        if ($actor['role'] === 'GIAO_VU' && !empty($actor['coSoId'])) {
            return (int)$actor['coSoId'];
        }
        return !empty($filters['coSoId']) ? (int)$filters['coSoId'] : null;
    }

    public static function meta(array $actor): array
    {
        $campusScope = ($actor['role'] === 'GIAO_VU' && !empty($actor['coSoId'])) ? (int)$actor['coSoId'] : null;

        $campuses = $campusScope !== null
            ? Store::rows('SELECT id, ma_co_so AS maCoSo, ten_co_so AS tenCoSo FROM co_so WHERE id=? ORDER BY ten_co_so', [$campusScope])
            : Store::rows('SELECT id, ma_co_so AS maCoSo, ten_co_so AS tenCoSo FROM co_so ORDER BY ten_co_so');

        $courses = Store::rows('SELECT id, ma, ten, is_active AS isActive FROM khoa_hoc ORDER BY id DESC');

        $classSql = 'SELECT id, ma_lop AS maLop, ten_lop AS tenLop, co_so_id AS coSoId, khoa_hoc_id AS khoaHocId, trang_thai AS trangThai
            FROM lop_hoc WHERE deleted_at IS NULL';
        $classParams = [];
        if ($campusScope !== null) {
            $classSql .= ' AND co_so_id=?';
            $classParams[] = $campusScope;
        }
        $classes = Store::rows($classSql.' ORDER BY id DESC', $classParams);

        $allowedReports = $actor['role'] === 'ADMIN'
            ? ['attendance', 'revenue', 'class-performance']
            : ['attendance', 'class-performance'];

        return [
            'campuses' => $campuses,
            'courses' => $courses,
            'classes' => $classes,
            'allowedReports' => $allowedReports,
            'scopedCampusId' => $campusScope,
        ];
    }

    /**
     * 1. Báo cáo Chuyên cần (Attendance Report)
     * Distinguishes: co_mat, di_tre, vang_co_phep, vang_khong_phep, chua_diem_danh, chua_dien_ra.
     * Never counts unrecorded or upcoming sessions as absent.
     */
    public static function attendance(array $filters, array $actor): array
    {
        $campusId = self::effectiveCampus($filters, $actor);
        $where = ['l.deleted_at IS NULL', 'b.ngay BETWEEN ? AND ?'];
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

        $baseWhere = implode(' AND ', $where);

        $cancelledCount = (int)Store::one(
            "SELECT COUNT(*) AS n FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id
             WHERE $baseWhere AND b.trang_thai IN ('huy','doi')",
            $params
        )['n'];

        $statusExpr = "CASE
            WHEN s.trang_thai_diem_danh IS NOT NULL THEN s.trang_thai_diem_danh
            WHEN b.trang_thai = 'du_kien' AND TIMESTAMP(b.ngay, b.gio_bat_dau) > NOW() THEN 'chua_dien_ra'
            ELSE 'chua_diem_danh'
        END";

        $slotFrom = "FROM buoi_hoc b
            JOIN lop_hoc l ON l.id = b.lop_id
            LEFT JOIN co_so cs ON cs.id = COALESCE(b.co_so_id, l.co_so_id)
            LEFT JOIN khoa_hoc k ON k.id = l.khoa_hoc_id
            JOIN mon_hoc m ON m.id = l.mon_hoc_id
            JOIN lop_hoc_vien x ON x.lop_id = l.id
              AND x.trang_thai <> 'cho_xep_lop'
              AND x.ngay_vao <= b.ngay
              AND (x.ngay_roi IS NULL OR x.ngay_roi >= b.ngay)
            JOIN hoc_vien hv ON hv.id = x.hoc_vien_id AND hv.deleted_at IS NULL
            LEFT JOIN so_theo_doi_buoi_hoc s ON s.buoi_hoc_id = b.id AND s.hoc_vien_id = hv.id
            WHERE $baseWhere
              AND b.trang_thai NOT IN ('huy','doi')
              AND (b.loai <> 'bu' OR b.pham_vi_bu <> 'ca_nhan' OR EXISTS (
                  SELECT 1 FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id = b.id AND hb.hoc_vien_id = hv.id
              ))";

        $slotParams = $params;
        $statusFilterSql = '';
        if (!empty($filters['trangThai'])) {
            $statusFilterSql = " AND ($statusExpr) = ?";
            $slotParams[] = $filters['trangThai'];
        }

        $groupRows = Store::rows(
            "SELECT
                l.id AS lopId, l.ma_lop AS maLop, l.ten_lop AS tenLop,
                COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                COALESCE(cs.ten_co_so, IF(l.hinh_thuc='online','Trực tuyến','Chưa gắn cơ sở')) AS tenCoSo,
                COUNT(DISTINCT b.id) AS soBuoi,
                COUNT(*) AS tongLuot,
                SUM(CASE WHEN ($statusExpr) = 'co_mat' THEN 1 ELSE 0 END) AS coMat,
                SUM(CASE WHEN ($statusExpr) = 'di_tre' THEN 1 ELSE 0 END) AS diTre,
                SUM(CASE WHEN ($statusExpr) = 'vang_co_phep' THEN 1 ELSE 0 END) AS vangCoPhep,
                SUM(CASE WHEN ($statusExpr) = 'vang_khong_phep' THEN 1 ELSE 0 END) AS vangKhongPhep,
                SUM(CASE WHEN ($statusExpr) = 'chua_diem_danh' THEN 1 ELSE 0 END) AS chuaDiemDanh,
                SUM(CASE WHEN ($statusExpr) = 'chua_dien_ra' THEN 1 ELSE 0 END) AS chuaDienRa
             $slotFrom $statusFilterSql
             GROUP BY l.id, l.ma_lop, l.ten_lop, tenKhoa, tenCoSo
             ORDER BY l.ma_lop ASC",
            $slotParams
        );

        $summary = [
            'tongLuot' => 0,
            'daDiemDanh' => 0,
            'coMat' => 0,
            'diTre' => 0,
            'vangCoPhep' => 0,
            'vangKhongPhep' => 0,
            'chuaDiemDanh' => 0,
            'chuaDienRa' => 0,
            'soBuoiHuyDoi' => $cancelledCount,
            'tyLeChuyenCan' => null,
        ];

        $byClass = [];
        foreach ($groupRows as $r) {
            $coMat = (int)$r['coMat'];
            $diTre = (int)$r['diTre'];
            $vcp = (int)$r['vangCoPhep'];
            $vkp = (int)$r['vangKhongPhep'];
            $cdd = (int)$r['chuaDiemDanh'];
            $cdr = (int)$r['chuaDienRa'];
            $daDd = $coMat + $diTre + $vcp + $vkp;
            $rate = $daDd > 0 ? round(($coMat + $diTre) * 100 / $daDd, 1) : null;

            $summary['tongLuot'] += (int)$r['tongLuot'];
            $summary['daDiemDanh'] += $daDd;
            $summary['coMat'] += $coMat;
            $summary['diTre'] += $diTre;
            $summary['vangCoPhep'] += $vcp;
            $summary['vangKhongPhep'] += $vkp;
            $summary['chuaDiemDanh'] += $cdd;
            $summary['chuaDienRa'] += $cdr;

            $byClass[] = [
                'lopId' => (int)$r['lopId'],
                'maLop' => $r['maLop'],
                'tenLop' => $r['tenLop'],
                'tenKhoa' => $r['tenKhoa'],
                'tenCoSo' => $r['tenCoSo'],
                'soBuoi' => (int)$r['soBuoi'],
                'tongLuot' => (int)$r['tongLuot'],
                'daDiemDanh' => $daDd,
                'coMat' => $coMat,
                'diTre' => $diTre,
                'vangCoPhep' => $vcp,
                'vangKhongPhep' => $vkp,
                'chuaDiemDanh' => $cdd,
                'chuaDienRa' => $cdr,
                'tyLeChuyenCan' => $rate,
            ];
        }
        if ($summary['daDiemDanh'] > 0) {
            $summary['tyLeChuyenCan'] = round(($summary['coMat'] + $summary['diTre']) * 100 / $summary['daDiemDanh'], 1);
        }

        $details = Store::rows(
            "SELECT b.id AS buoiHocId, b.ngay, b.gio_bat_dau AS gioBatDau, b.gio_ket_thuc AS gioKetThuc,
                    b.trang_thai AS trangThaiBuoi, b.loai AS loaiBuoi,
                    l.ma_lop AS maLop, l.ten_lop AS tenLop,
                    COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                    COALESCE(cs.ten_co_so, IF(l.hinh_thuc='online','Trực tuyến','Chưa gắn cơ sở')) AS tenCoSo,
                    hv.ma_hv AS maHv, hv.ho_ten AS hoTen,
                    ($statusExpr) AS trangThaiDiemDanh,
                    s.diem_bai_tap_giay AS diemBaiTap,
                    s.nhan_xet_buoi_hoc AS nhanXet
             $slotFrom $statusFilterSql
             ORDER BY b.ngay DESC, b.gio_bat_dau DESC, l.ma_lop ASC, hv.ho_ten ASC
             LIMIT ".self::DETAIL_LIMIT,
            $slotParams
        );

        return [
            'report' => 'attendance',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byClass' => $byClass,
            'details' => $details,
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Tỷ lệ chuyên cần (%) = (Có mặt + Đi trễ) / Tổng lượt đã điểm danh × 100.',
                'Lượt chưa diễn ra (buổi dự kiến trong tương lai) và chưa điểm danh được tách riêng, tuyệt đối không tính vào vắng mặt.',
                'Các buổi đã hủy hoặc đổi lịch không tính vào mẫu số chuyên cần.',
            ],
        ];
    }

    /**
     * 2. Báo cáo Doanh thu theo khóa học và cơ sở (Revenue Report — Admin only).
     * Strictly confirmed valid receipts (thanh_toan.trang_thai = 'hop_le').
     * Never fabricates course or campus when DB relation is NULL.
     */
    public static function revenue(array $filters, array $actor): array
    {
        $campusId = self::effectiveCampus($filters, $actor);

        // Helper SQL resolving exact course and exact campus from real FK relations (no guessing)
        $invoiceJoins = "FROM hoa_don hd
            LEFT JOIN dang_ky_khoa_hoc dk ON dk.id = hd.dang_ky_khoa_hoc_id
            LEFT JOIN lop_hoc_vien lhv_leg ON lhv_leg.id = hd.lop_hoc_vien_id
            LEFT JOIN lop_hoc l_leg ON l_leg.id = lhv_leg.lop_id AND l_leg.deleted_at IS NULL
            LEFT JOIN lop_hoc l_dk ON l_dk.id = (
                SELECT x.lop_id FROM lop_hoc_vien x
                WHERE x.dang_ky_khoa_hoc_id = dk.id
                ORDER BY FIELD(x.trang_thai,'dang_hoc','hoc_thu','tam_nghi','cho_xep_lop','da_roi'), x.id DESC
                LIMIT 1
            ) AND l_dk.deleted_at IS NULL
            LEFT JOIN khoa_hoc k ON k.id = COALESCE(dk.khoa_hoc_id, l_leg.khoa_hoc_id)
            LEFT JOIN co_so cs ON cs.id = COALESCE(l_dk.co_so_id, l_leg.co_so_id)
            LEFT JOIN hoc_vien hv ON hv.id = COALESCE(dk.hoc_vien_id, lhv_leg.hoc_vien_id)";

        $commonWhere = [];
        $commonParams = [];
        if ($campusId !== null) {
            $commonWhere[] = 'COALESCE(l_dk.co_so_id, l_leg.co_so_id) = ?';
            $commonParams[] = $campusId;
        }
        if (!empty($filters['khoaHocId'])) {
            $commonWhere[] = 'COALESCE(dk.khoa_hoc_id, l_leg.khoa_hoc_id) = ?';
            $commonParams[] = (int)$filters['khoaHocId'];
        }
        if (!empty($filters['lopId'])) {
            $commonWhere[] = 'COALESCE(l_dk.id, l_leg.id) = ?';
            $commonParams[] = (int)$filters['lopId'];
        }
        if (!empty($filters['trangThai'])) {
            $commonWhere[] = 'hd.trang_thai = ?';
            $commonParams[] = $filters['trangThai'];
        }
        $extraFilterSql = $commonWhere ? ' AND '.implode(' AND ', $commonWhere) : '';

        // 2A. Issued receivables (hoa_don) in date range
        $invRows = Store::rows(
            "SELECT
                COALESCE(k.id, 0) AS khoaHocId,
                COALESCE(k.ma, 'CHUA_GAN') AS maKhoa,
                COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                COALESCE(cs.id, 0) AS coSoId,
                COALESCE(cs.ten_co_so, 'Chưa xếp cơ sở / Trực tuyến') AS tenCoSo,
                COUNT(DISTINCT hd.id) AS soHoaDon,
                COALESCE(SUM(hd.phai_thu), 0) AS tongPhaiThu,
                COALESCE(SUM(GREATEST(0, hd.phai_thu - hd.da_thu)), 0) AS tongConNo
             $invoiceJoins
             WHERE hd.trang_thai NOT IN ('nhap', 'huy')
               AND DATE(COALESCE(hd.phat_hanh_luc, hd.created_at)) BETWEEN ? AND ?
               $extraFilterSql
             GROUP BY khoaHocId, maKhoa, tenKhoa, coSoId, tenCoSo",
            [$filters['tuNgay'], $filters['denNgay'], ...$commonParams]
        );

        // 2B. Confirmed actual receipts/refunds (thanh_toan.trang_thai = 'hop_le') in date range
        $payRows = Store::rows(
            "SELECT
                COALESCE(k.id, 0) AS khoaHocId,
                COALESCE(k.ma, 'CHUA_GAN') AS maKhoa,
                COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                COALESCE(cs.id, 0) AS coSoId,
                COALESCE(cs.ten_co_so, 'Chưa xếp cơ sở / Trực tuyến') AS tenCoSo,
                COUNT(t.id) AS soPhieu,
                COALESCE(SUM(CASE WHEN t.loai = 'thu' THEN t.so_tien ELSE 0 END), 0) AS tongThu,
                COALESCE(SUM(CASE WHEN t.loai = 'hoan' THEN t.so_tien ELSE 0 END), 0) AS tongHoan,
                COALESCE(SUM(CASE WHEN t.loai = 'thu' THEN t.so_tien ELSE -t.so_tien END), 0) AS thucThuRong
             FROM thanh_toan t
             JOIN hoa_don hd ON hd.id = t.hoa_don_id
             LEFT JOIN dang_ky_khoa_hoc dk ON dk.id = hd.dang_ky_khoa_hoc_id
             LEFT JOIN lop_hoc_vien lhv_leg ON lhv_leg.id = hd.lop_hoc_vien_id
             LEFT JOIN lop_hoc l_leg ON l_leg.id = lhv_leg.lop_id AND l_leg.deleted_at IS NULL
             LEFT JOIN lop_hoc l_dk ON l_dk.id = (
                 SELECT x.lop_id FROM lop_hoc_vien x
                 WHERE x.dang_ky_khoa_hoc_id = dk.id
                 ORDER BY FIELD(x.trang_thai,'dang_hoc','hoc_thu','tam_nghi','cho_xep_lop','da_roi'), x.id DESC
                 LIMIT 1
             ) AND l_dk.deleted_at IS NULL
             LEFT JOIN khoa_hoc k ON k.id = COALESCE(dk.khoa_hoc_id, l_leg.khoa_hoc_id)
             LEFT JOIN co_so cs ON cs.id = COALESCE(l_dk.co_so_id, l_leg.co_so_id)
             WHERE t.trang_thai = 'hop_le'
               AND DATE(t.ngay_thanh_toan) BETWEEN ? AND ?
               $extraFilterSql
             GROUP BY khoaHocId, maKhoa, tenKhoa, coSoId, tenCoSo",
            [$filters['tuNgay'], $filters['denNgay'], ...$commonParams]
        );

        // Merge receivables and confirmed actuals by (khoaHocId, coSoId)
        $merged = [];
        foreach ($invRows as $r) {
            $key = $r['khoaHocId'].':'.$r['coSoId'];
            $merged[$key] = [
                'khoaHocId' => (int)$r['khoaHocId'],
                'maKhoa' => $r['maKhoa'],
                'tenKhoa' => $r['tenKhoa'],
                'coSoId' => (int)$r['coSoId'],
                'tenCoSo' => $r['tenCoSo'],
                'soHoaDon' => (int)$r['soHoaDon'],
                'phaiThu' => (int)$r['tongPhaiThu'],
                'conNo' => (int)$r['tongConNo'],
                'soPhieu' => 0,
                'tongThu' => 0,
                'tongHoan' => 0,
                'thucThuRong' => 0,
            ];
        }
        foreach ($payRows as $r) {
            $key = $r['khoaHocId'].':'.$r['coSoId'];
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'khoaHocId' => (int)$r['khoaHocId'],
                    'maKhoa' => $r['maKhoa'],
                    'tenKhoa' => $r['tenKhoa'],
                    'coSoId' => (int)$r['coSoId'],
                    'tenCoSo' => $r['tenCoSo'],
                    'soHoaDon' => 0,
                    'phaiThu' => 0,
                    'conNo' => 0,
                    'soPhieu' => 0,
                    'tongThu' => 0,
                    'tongHoan' => 0,
                    'thucThuRong' => 0,
                ];
            }
            $merged[$key]['soPhieu'] = (int)$r['soPhieu'];
            $merged[$key]['tongThu'] = (int)$r['tongThu'];
            $merged[$key]['tongHoan'] = (int)$r['tongHoan'];
            $merged[$key]['thucThuRong'] = (int)$r['thucThuRong'];
        }
        uasort($merged, static fn(array $a, array $b) => ($b['thucThuRong'] <=> $a['thucThuRong']) ?: ($b['phaiThu'] <=> $a['phaiThu']) ?: strcmp($a['tenKhoa'], $b['tenKhoa']));
        $byGroup = array_values($merged);

        // Informational pending online intents (explicitly NOT included in actual revenue)
        $pendingOnline = Store::one(
            "SELECT COUNT(*) AS soGiaoDich, COALESCE(SUM(g.so_tien), 0) AS tongTien
             FROM giao_dich_truc_tuyen g
             JOIN hoa_don hd ON hd.id = g.hoa_don_id
             LEFT JOIN dang_ky_khoa_hoc dk ON dk.id = hd.dang_ky_khoa_hoc_id
             LEFT JOIN lop_hoc_vien lhv_leg ON lhv_leg.id = hd.lop_hoc_vien_id
             LEFT JOIN lop_hoc l_leg ON l_leg.id = lhv_leg.lop_id AND l_leg.deleted_at IS NULL
             LEFT JOIN lop_hoc l_dk ON l_dk.id = (
                 SELECT x.lop_id FROM lop_hoc_vien x
                 WHERE x.dang_ky_khoa_hoc_id = dk.id
                 ORDER BY FIELD(x.trang_thai,'dang_hoc','hoc_thu','tam_nghi','cho_xep_lop','da_roi'), x.id DESC
                 LIMIT 1
             ) AND l_dk.deleted_at IS NULL
             WHERE g.trang_thai IN ('khoi_tao','cho_xac_nhan')
               AND DATE(g.created_at) BETWEEN ? AND ?
               $extraFilterSql",
            [$filters['tuNgay'], $filters['denNgay'], ...$commonParams]
        );

        $summary = [
            'soHoaDon' => array_sum(array_column($byGroup, 'soHoaDon')),
            'phaiThu' => array_sum(array_column($byGroup, 'phaiThu')),
            'conNo' => array_sum(array_column($byGroup, 'conNo')),
            'soPhieuXacNhan' => array_sum(array_column($byGroup, 'soPhieu')),
            'tongThu' => array_sum(array_column($byGroup, 'tongThu')),
            'tongHoan' => array_sum(array_column($byGroup, 'tongHoan')),
            'thucThuRong' => array_sum(array_column($byGroup, 'thucThuRong')),
            'giaoDichChoDoiSoat' => (int)($pendingOnline['soGiaoDich'] ?? 0),
            'tienChoDoiSoat' => (int)($pendingOnline['tongTien'] ?? 0),
        ];

        $details = Store::rows(
            "SELECT t.id AS phieuId, t.ngay_thanh_toan AS ngayThanhToan, t.loai, t.so_tien AS soTien,
                    t.phuong_thuc AS phuongThuc, t.ma_giao_dich AS maGiaoDich,
                    hd.ma_hd AS maHd, hd.trang_thai AS trangThaiHoaDon, hd.phai_thu AS phaiThu, hd.da_thu AS daThu,
                    COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                    COALESCE(cs.ten_co_so, 'Chưa xếp cơ sở / Trực tuyến') AS tenCoSo,
                    COALESCE(l_dk.ma_lop, l_leg.ma_lop, 'Chưa xếp lớp') AS maLop,
                    COALESCE(hv.ho_ten, '—') AS tenHocVien,
                    u_thu.hoten AS nguoiThu
             FROM thanh_toan t
             JOIN hoa_don hd ON hd.id = t.hoa_don_id
             JOIN users u_thu ON u_thu.user_id = t.nguoi_thu_id
             LEFT JOIN dang_ky_khoa_hoc dk ON dk.id = hd.dang_ky_khoa_hoc_id
             LEFT JOIN lop_hoc_vien lhv_leg ON lhv_leg.id = hd.lop_hoc_vien_id
             LEFT JOIN lop_hoc l_leg ON l_leg.id = lhv_leg.lop_id AND l_leg.deleted_at IS NULL
             LEFT JOIN lop_hoc l_dk ON l_dk.id = (
                 SELECT x.lop_id FROM lop_hoc_vien x
                 WHERE x.dang_ky_khoa_hoc_id = dk.id
                 ORDER BY FIELD(x.trang_thai,'dang_hoc','hoc_thu','tam_nghi','cho_xep_lop','da_roi'), x.id DESC
                 LIMIT 1
             ) AND l_dk.deleted_at IS NULL
             LEFT JOIN khoa_hoc k ON k.id = COALESCE(dk.khoa_hoc_id, l_leg.khoa_hoc_id)
             LEFT JOIN co_so cs ON cs.id = COALESCE(l_dk.co_so_id, l_leg.co_so_id)
             LEFT JOIN hoc_vien hv ON hv.id = COALESCE(dk.hoc_vien_id, lhv_leg.hoc_vien_id)
             WHERE t.trang_thai = 'hop_le'
               AND DATE(t.ngay_thanh_toan) BETWEEN ? AND ?
               $extraFilterSql
             ORDER BY t.ngay_thanh_toan DESC, t.id DESC
             LIMIT ".self::DETAIL_LIMIT,
            [$filters['tuNgay'], $filters['denNgay'], ...$commonParams]
        );

        return [
            'report' => 'revenue',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byGroup' => $byGroup,
            'details' => $details,
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Thực thu ròng = Tổng phiếu thu (loai = thu) − Tổng phiếu hoàn (loai = hoan) của các phiếu thanh_toan có trang_thai = hop_le trong khoảng ngày thanh toán.',
                'Phải thu = Tổng phai_thu của hóa đơn đã phát hành (trang_thai khác nhap/huy) trong khoảng ngày phát hành.',
                'Các giao dịch trực tuyến đang chờ xác nhận/khởi tạo hoặc phiếu đã hủy tuyệt đối không tính vào Thực thu.',
                'Học viên đăng ký khóa chưa xếp lớp hoặc lớp học trực tuyến giữ nhãn "Chưa xếp cơ sở / Trực tuyến", không tự suy diễn cơ sở.',
            ],
        ];
    }

    /**
     * 3. Báo cáo Hiệu suất lớp (Class Performance Report).
     * Purely objective DB metrics (no invented subjective scores).
     */
    public static function classPerformance(array $filters, array $actor): array
    {
        $campusId = self::effectiveCampus($filters, $actor);
        $where = ['l.deleted_at IS NULL'];
        $params = [];

        if ($campusId !== null) {
            $where[] = 'l.co_so_id = ?';
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
        if (!empty($filters['trangThai'])) {
            $where[] = 'l.trang_thai = ?';
            $params[] = $filters['trangThai'];
        }
        $whereSql = implode(' AND ', $where);

        $classRows = Store::rows(
            "SELECT
                l.id AS lopId, l.ma_lop AS maLop, l.ten_lop AS tenLop, l.loai_lop AS loaiLop,
                l.trang_thai AS trangThai, l.si_so_toi_da AS siSoToiDa,
                COALESCE(k.ten, 'Chưa gắn khóa học') AS tenKhoa,
                COALESCE(cs.ten_co_so, IF(l.hinh_thuc='online','Trực tuyến','Chưa gắn cơ sở')) AS tenCoSo,
                m.ten AS tenMon, cl.ten AS tenCapLop, u_gv.hoten AS nguoiDayChinh,
                (SELECT COUNT(*) FROM lop_hoc_vien x JOIN hoc_vien hv ON hv.id=x.hoc_vien_id
                 WHERE x.lop_id=l.id AND hv.deleted_at IS NULL AND x.trang_thai IN ('dang_hoc','hoc_thu')) AS siSoDangHoc,
                COALESCE(bs.tong_buoi, 0) AS tongBuoiTrongKy,
                COALESCE(bs.da_dien_ra, 0) AS soBuoiDaDienRa,
                COALESCE(bs.dang_day, 0) AS soBuoiDangDay,
                COALESCE(bs.sap_dien_ra, 0) AS soBuoiSapDienRa,
                COALESCE(bs.bi_huy, 0) AS soBuoiBiHuy,
                COALESCE(bs.doi_lich, 0) AS soBuoiDoiLich,
                COALESCE(bs.hoc_bu, 0) AS soBuoiHocBu,
                COALESCE(bs.co_so_hoc_vu, 0) AS soBuoiCoSoHocVu,
                COALESCE(att.da_diem_danh, 0) AS luotDaDiemDanh,
                COALESCE(att.co_mat_hoac_tre, 0) AS luotCoMatHoacTre,
                COALESCE(att.vang, 0) AS luotVang
             FROM lop_hoc l
             JOIN mon_hoc m ON m.id = l.mon_hoc_id
             JOIN cap_lop cl ON cl.id = l.cap_lop_id
             JOIN nhan_su_chuyen_mon ns ON ns.id = l.nguoi_day_chinh_id
             JOIN users u_gv ON u_gv.user_id = ns.user_id
             LEFT JOIN khoa_hoc k ON k.id = l.khoa_hoc_id
             LEFT JOIN co_so cs ON cs.id = l.co_so_id
             LEFT JOIN (
                 SELECT b.lop_id,
                        COUNT(*) AS tong_buoi,
                        SUM(CASE WHEN b.trang_thai = 'da_day' THEN 1 ELSE 0 END) AS da_dien_ra,
                        SUM(CASE WHEN b.trang_thai = 'dang_day' THEN 1 ELSE 0 END) AS dang_day,
                        SUM(CASE WHEN b.trang_thai = 'du_kien' THEN 1 ELSE 0 END) AS sap_dien_ra,
                        SUM(CASE WHEN b.trang_thai = 'huy' THEN 1 ELSE 0 END) AS bi_huy,
                        SUM(CASE WHEN b.trang_thai = 'doi' THEN 1 ELSE 0 END) AS doi_lich,
                        SUM(CASE WHEN b.loai = 'bu' AND b.trang_thai NOT IN ('huy','doi') THEN 1 ELSE 0 END) AS hoc_bu,
                        SUM(CASE WHEN EXISTS (SELECT 1 FROM so_theo_doi_buoi_hoc s WHERE s.buoi_hoc_id = b.id) THEN 1 ELSE 0 END) AS co_so_hoc_vu
                 FROM buoi_hoc b
                 WHERE b.ngay BETWEEN ? AND ?
                 GROUP BY b.lop_id
             ) bs ON bs.lop_id = l.id
             LEFT JOIN (
                 SELECT b.lop_id,
                        COUNT(s.id) AS da_diem_danh,
                        SUM(CASE WHEN s.trang_thai_diem_danh IN ('co_mat','di_tre') THEN 1 ELSE 0 END) AS co_mat_hoac_tre,
                        SUM(CASE WHEN s.trang_thai_diem_danh IN ('vang_co_phep','vang_khong_phep') THEN 1 ELSE 0 END) AS vang
                 FROM buoi_hoc b
                 JOIN so_theo_doi_buoi_hoc s ON s.buoi_hoc_id = b.id
                 WHERE b.ngay BETWEEN ? AND ?
                   AND b.trang_thai NOT IN ('huy','doi')
                 GROUP BY b.lop_id
             ) att ON att.lop_id = l.id
             WHERE $whereSql
             ORDER BY l.ma_lop ASC",
            [$filters['tuNgay'], $filters['denNgay'], $filters['tuNgay'], $filters['denNgay'], ...$params]
        );

        $classes = [];
        $totActive = 0;
        $totMax = 0;
        $totHeld = 0;
        $totCancel = 0;
        $totMakeup = 0;
        $totMarked = 0;
        $totPresent = 0;
        $totSessions = 0;

        foreach ($classRows as $r) {
            $active = (int)$r['siSoDangHoc'];
            $max = max(1, (int)$r['siSoToiDa']);
            $marked = (int)$r['luotDaDiemDanh'];
            $present = (int)$r['luotCoMatHoacTre'];
            $fillRate = round($active * 100 / $max, 1);
            $attRate = $marked > 0 ? round($present * 100 / $marked, 1) : null;

            $totActive += $active;
            $totMax += (int)$r['siSoToiDa'];
            $totSessions += (int)$r['tongBuoiTrongKy'];
            $totHeld += (int)$r['soBuoiDaDienRa'];
            $totCancel += (int)$r['soBuoiBiHuy'];
            $totMakeup += (int)$r['soBuoiHocBu'];
            $totMarked += $marked;
            $totPresent += $present;

            $classes[] = [
                'lopId' => (int)$r['lopId'],
                'maLop' => $r['maLop'],
                'tenLop' => $r['tenLop'],
                'loaiLop' => $r['loaiLop'],
                'trangThai' => $r['trangThai'],
                'tenKhoa' => $r['tenKhoa'],
                'tenCoSo' => $r['tenCoSo'],
                'tenMon' => $r['tenMon'],
                'tenCapLop' => $r['tenCapLop'],
                'nguoiDayChinh' => $r['nguoiDayChinh'],
                'siSoDangHoc' => $active,
                'siSoToiDa' => (int)$r['siSoToiDa'],
                'tyLeLapDay' => $fillRate,
                'tongBuoiTrongKy' => (int)$r['tongBuoiTrongKy'],
                'soBuoiDaDienRa' => (int)$r['soBuoiDaDienRa'],
                'soBuoiDangDay' => (int)$r['soBuoiDangDay'],
                'soBuoiSapDienRa' => (int)$r['soBuoiSapDienRa'],
                'soBuoiBiHuy' => (int)$r['soBuoiBiHuy'],
                'soBuoiDoiLich' => (int)$r['soBuoiDoiLich'],
                'soBuoiHocBu' => (int)$r['soBuoiHocBu'],
                'soBuoiCoSoHocVu' => (int)$r['soBuoiCoSoHocVu'],
                'luotDaDiemDanh' => $marked,
                'luotCoMatHoacTre' => $present,
                'luotVang' => (int)$r['luotVang'],
                'tyLeChuyenCan' => $attRate,
            ];
        }

        $details = Store::rows(
            "SELECT b.id AS buoiHocId, b.ngay, b.gio_bat_dau AS gioBatDau, b.gio_ket_thuc AS gioKetThuc,
                    b.loai AS loaiBuoi, b.trang_thai AS trangThaiBuoi,
                    l.ma_lop AS maLop, l.ten_lop AS tenLop,
                    COALESCE(cs.ten_co_so, IF(l.hinh_thuc='online','Trực tuyến','Chưa gắn cơ sở')) AS tenCoSo,
                    (SELECT COUNT(*) FROM so_theo_doi_buoi_hoc s WHERE s.buoi_hoc_id=b.id) AS soLuotDiemDanh,
                    (SELECT COUNT(*) FROM so_theo_doi_buoi_hoc s WHERE s.buoi_hoc_id=b.id AND s.trang_thai_diem_danh IN ('co_mat','di_tre')) AS soCoMatHoacTre
             FROM buoi_hoc b
             JOIN lop_hoc l ON l.id = b.lop_id
             LEFT JOIN co_so cs ON cs.id = COALESCE(b.co_so_id, l.co_so_id)
             WHERE $whereSql AND b.ngay BETWEEN ? AND ?
             ORDER BY b.ngay DESC, b.gio_bat_dau DESC, l.ma_lop ASC
             LIMIT ".self::DETAIL_LIMIT,
            [...$params, $filters['tuNgay'], $filters['denNgay']]
        );

        return [
            'report' => 'class-performance',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => [
                'soLop' => count($classes),
                'tongSiSoDangHoc' => $totActive,
                'tongSucChua' => $totMax,
                'tyLeLapDayTrungBinh' => $totMax > 0 ? round($totActive * 100 / $totMax, 1) : null,
                'tongBuoiTrongKy' => $totSessions,
                'soBuoiDaDienRa' => $totHeld,
                'soBuoiBiHuy' => $totCancel,
                'soBuoiHocBu' => $totMakeup,
                'tyLeChuyenCan' => $totMarked > 0 ? round($totPresent * 100 / $totMarked, 1) : null,
            ],
            'classes' => $classes,
            'details' => $details,
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Sĩ số đang học = Số học viên có ghi danh lop_hoc_vien ở trạng thái dang_hoc hoặc hoc_thu (chưa bị xóa mềm).',
                'Tỷ lệ lấp đầy (%) = Sĩ số đang học / Sĩ số tối đa × 100.',
                'Số buổi đã diễn ra = Số buổi buoi_hoc trong khoảng ngày có trang_thai = da_day; Số buổi bị hủy = trang_thai = huy.',
                'Tỷ lệ chuyên cần (%) = Số lượt điểm danh co_mat hoặc di_tre / Tổng lượt đã điểm danh trong khoảng ngày × 100.',
                'Không sử dụng chỉ số đánh giá chủ quan ngoài dữ liệu thực tế lưu trong DB.',
            ],
        ];
    }
}
