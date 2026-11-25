<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\{ApiException, Store};

final class ReportsRepository
{
    private const DETAIL_LIMIT = 5000;

    private static function dateBounds(string $tuNgay, string $denNgay): array
    {
        $tz = new \DateTimeZone('Asia/Ho_Chi_Minh');
        $toNext = (new \DateTimeImmutable($denNgay, $tz))->modify('+1 day')->format('Y-m-d');
        return [$tuNgay.' 00:00:00', $toNext.' 00:00:00'];
    }

    public static function enforceScope(array $filters, array $actor): ?int
    {
        if ($actor['role'] === 'GIAO_VU' && !empty($actor['coSoId'])) {
            $scoped = (int)$actor['coSoId'];
            if (!empty($filters['coSoId']) && (int)$filters['coSoId'] !== $scoped) {
                throw new ApiException(403, 'Bạn chỉ được xem báo cáo trong phạm vi cơ sở phụ trách.', 'FORBIDDEN');
            }
            if (!empty($filters['lopId'])) {
                $cls = Store::one('SELECT co_so_id FROM lop_hoc WHERE id = ? AND deleted_at IS NULL', [(int)$filters['lopId']]);
                if ($cls && (int)($cls['co_so_id'] ?? 0) !== $scoped) {
                    throw new ApiException(403, 'Lớp học được chọn không thuộc cơ sở bạn phụ trách.', 'FORBIDDEN');
                }
            }
            return $scoped;
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
        $subjects = Store::rows('SELECT id, ma, ten FROM mon_hoc WHERE is_active = 1 ORDER BY ten ASC');

        $classSql = 'SELECT id, ma_lop AS maLop, ten_lop AS tenLop, co_so_id AS coSoId, khoa_hoc_id AS khoaHocId, trang_thai AS trangThai
            FROM lop_hoc WHERE deleted_at IS NULL';
        $classParams = [];
        if ($campusScope !== null) {
            $classSql .= ' AND co_so_id=?';
            $classParams[] = $campusScope;
        }
        $classes = Store::rows($classSql.' ORDER BY id DESC', $classParams);

        $allowedReports = $actor['role'] === 'ADMIN'
            ? ['attendance', 'revenue', 'class-performance', 'payroll', 'debt', 'admissions']
            : ['attendance', 'class-performance', 'admissions'];

        $canDownload = $actor['role'] === 'ADMIN';
        if (!$canDownload && $actor['role'] === 'GIAO_VU') {
            foreach ($actor['permissions'] ?? [] as $perm) {
                if (($perm['functionName'] ?? '') === 'BaoCaoThongKe' && !empty($perm['download'])) {
                    $canDownload = true;
                    break;
                }
            }
        }

        return [
            'campuses' => $campuses,
            'courses' => $courses,
            'subjects' => $subjects,
            'classes' => $classes,
            'allowedReports' => $allowedReports,
            'scopedCampusId' => $campusScope,
            'canDownload' => $canDownload,
        ];
    }

    /**
     * 1. Báo cáo Chuyên cần (Attendance Report)
     * Distinguishes: co_mat, di_tre, vang_co_phep, vang_khong_phep, chua_diem_danh, chua_dien_ra.
     * Never counts unrecorded or upcoming sessions as absent.
     */
    public static function attendance(array $filters, array $actor): array
    {
        $campusId = self::enforceScope($filters, $actor);
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
                'Các buổi đã hủy hoặc đổi lịch không tính vào mẫu số chuyên cần; buổi học bù cá nhân chỉ tính học viên có tên trong hoc_bu_hoc_vien.',
            ],
        ];
    }

    private static function invoiceRelationJoins(): string
    {
        return "LEFT JOIN dang_ky_khoa_hoc dk ON dk.id = hd.dang_ky_khoa_hoc_id
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
    }

    /**
     * 2. Báo cáo Doanh thu theo khóa học và cơ sở (Revenue Report — Admin only).
     * Strictly confirmed valid receipts (thanh_toan.trang_thai = 'hop_le').
     * Index-friendly datetime bounds in Asia/Ho_Chi_Minh; never fabricates course or campus.
     */
    public static function revenue(array $filters, array $actor): array
    {
        $campusId = self::enforceScope($filters, $actor);
        [$fromTs, $toNextTs] = self::dateBounds($filters['tuNgay'], $filters['denNgay']);
        $relJoins = self::invoiceRelationJoins();

        $scopeWhere = [];
        $scopeParams = [];
        if ($campusId !== null) {
            $scopeWhere[] = 'COALESCE(l_dk.co_so_id, l_leg.co_so_id) = ?';
            $scopeParams[] = $campusId;
        }
        if (!empty($filters['khoaHocId'])) {
            $scopeWhere[] = 'COALESCE(dk.khoa_hoc_id, l_leg.khoa_hoc_id) = ?';
            $scopeParams[] = (int)$filters['khoaHocId'];
        }
        if (!empty($filters['lopId'])) {
            $scopeWhere[] = 'COALESCE(l_dk.id, l_leg.id) = ?';
            $scopeParams[] = (int)$filters['lopId'];
        }
        $scopeFilterSql = $scopeWhere ? ' AND '.implode(' AND ', $scopeWhere) : '';

        $commonWhere = $scopeWhere;
        $commonParams = $scopeParams;
        if (!empty($filters['trangThai'])) {
            $commonWhere[] = 'hd.trang_thai = ?';
            $commonParams[] = $filters['trangThai'];
        }
        $extraFilterSql = $commonWhere ? ' AND '.implode(' AND ', $commonWhere) : '';

        // 2A. Issued receivables (hoa_don) in date range (index-friendly range without DATE())
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
             FROM hoa_don hd
             $relJoins
             WHERE hd.trang_thai NOT IN ('nhap', 'huy')
               AND ((hd.phat_hanh_luc IS NOT NULL AND hd.phat_hanh_luc >= ? AND hd.phat_hanh_luc < ?)
                    OR (hd.phat_hanh_luc IS NULL AND hd.created_at >= ? AND hd.created_at < ?))
               $extraFilterSql
             GROUP BY khoaHocId, maKhoa, tenKhoa, coSoId, tenCoSo",
            [$fromTs, $toNextTs, $fromTs, $toNextTs, ...$commonParams]
        );

        // 2B. Confirmed actual receipts/refunds (thanh_toan.trang_thai = 'hop_le') using idx_tt_ngay
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
             $relJoins
             WHERE t.trang_thai = 'hop_le'
               AND t.ngay_thanh_toan >= ? AND t.ngay_thanh_toan < ?
               $extraFilterSql
             GROUP BY khoaHocId, maKhoa, tenKhoa, coSoId, tenCoSo",
            [$fromTs, $toNextTs, ...$commonParams]
        );

        // 2C. Trend series (by day if <= 45 days, else by month) from confirmed receipts
        $diffDays = (int)(new \DateTimeImmutable($filters['tuNgay']))->diff(new \DateTimeImmutable($filters['denNgay']))->days;
        $granularity = $diffDays <= 45 ? 'day' : 'month';
        $bucketExpr = $granularity === 'day' ? "DATE_FORMAT(t.ngay_thanh_toan, '%Y-%m-%d')" : "DATE_FORMAT(t.ngay_thanh_toan, '%Y-%m')";
        $trendRaw = Store::rows(
            "SELECT $bucketExpr AS period,
                    COALESCE(SUM(CASE WHEN t.loai = 'thu' THEN t.so_tien ELSE 0 END), 0) AS tongThu,
                    COALESCE(SUM(CASE WHEN t.loai = 'hoan' THEN t.so_tien ELSE 0 END), 0) AS tongHoan,
                    COALESCE(SUM(CASE WHEN t.loai = 'thu' THEN t.so_tien ELSE -t.so_tien END), 0) AS thucThuRong,
                    COUNT(t.id) AS soPhieu
             FROM thanh_toan t
             JOIN hoa_don hd ON hd.id = t.hoa_don_id
             $relJoins
             WHERE t.trang_thai = 'hop_le'
               AND t.ngay_thanh_toan >= ? AND t.ngay_thanh_toan < ?
               $extraFilterSql
             GROUP BY period
             ORDER BY period ASC",
            [$fromTs, $toNextTs, ...$commonParams]
        );
        $trendMap = [];
        foreach ($trendRaw as $tr) {
            $trendMap[$tr['period']] = [
                'period' => $tr['period'],
                'tongThu' => (int)$tr['tongThu'],
                'tongHoan' => (int)$tr['tongHoan'],
                'thucThuRong' => (int)$tr['thucThuRong'],
                'soPhieu' => (int)$tr['soPhieu'],
            ];
        }
        $trend = [];
        $tz = new \DateTimeZone('Asia/Ho_Chi_Minh');
        if ($granularity === 'month') {
            $cursor = new \DateTimeImmutable(substr($filters['tuNgay'], 0, 7).'-01', $tz);
            $endCursor = new \DateTimeImmutable(substr($filters['denNgay'], 0, 7).'-01', $tz);
            while ($cursor <= $endCursor) {
                $k = $cursor->format('Y-m');
                $trend[] = $trendMap[$k] ?? ['period' => $k, 'tongThu' => 0, 'tongHoan' => 0, 'thucThuRong' => 0, 'soPhieu' => 0];
                $cursor = $cursor->modify('+1 month');
            }
        } else {
            $cursor = new \DateTimeImmutable($filters['tuNgay'], $tz);
            $endCursor = new \DateTimeImmutable($filters['denNgay'], $tz);
            while ($cursor <= $endCursor) {
                $k = $cursor->format('Y-m-d');
                $trend[] = $trendMap[$k] ?? ['period' => $k, 'tongThu' => 0, 'tongHoan' => 0, 'thucThuRong' => 0, 'soPhieu' => 0];
                $cursor = $cursor->modify('+1 day');
            }
        }

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
             $relJoins
             WHERE g.trang_thai IN ('khoi_tao','cho_xac_nhan')
               AND g.created_at >= ? AND g.created_at < ?
               $extraFilterSql",
            [$fromTs, $toNextTs, ...$commonParams]
        );

        // Separate current total outstanding debt across ALL unpaid/partial invoices (NOT restricted by date range)
        $outstandingAll = Store::one(
            "SELECT COUNT(*) AS soHoaDon, COALESCE(SUM(GREATEST(0, hd.phai_thu - hd.da_thu)), 0) AS tongTien
             FROM hoa_don hd
             $relJoins
             WHERE hd.trang_thai IN ('chua_thanh_toan','mot_phan')
               $scopeFilterSql",
            $scopeParams
        );

        $summary = [
            'soHoaDon' => array_sum(array_column($byGroup, 'soHoaDon')),
            'phaiThu' => array_sum(array_column($byGroup, 'phaiThu')),
            'conNo' => array_sum(array_column($byGroup, 'conNo')),
            'tongCongNoTonHienHanh' => (int)($outstandingAll['tongTien'] ?? 0),
            'soHoaDonNoTonHienHanh' => (int)($outstandingAll['soHoaDon'] ?? 0),
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
             $relJoins
             WHERE t.trang_thai = 'hop_le'
               AND t.ngay_thanh_toan >= ? AND t.ngay_thanh_toan < ?
               $extraFilterSql
             ORDER BY t.ngay_thanh_toan DESC, t.id DESC
             LIMIT ".self::DETAIL_LIMIT,
            [$fromTs, $toNextTs, ...$commonParams]
        );

        return [
            'report' => 'revenue',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byGroup' => $byGroup,
            'trend' => $trend,
            'trendGranularity' => $granularity,
            'details' => $details,
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Thực thu ròng = Tổng phiếu thu (loai = thu) − Tổng phiếu hoàn (loai = hoan) của các phiếu thanh_toan có trang_thai = hop_le trong khoảng ngày thanh toán (múi giờ Asia/Ho_Chi_Minh).',
                'Phải thu & Còn nợ trong kỳ tính trên các hóa đơn đã phát hành (khác nhap/huy) trong khoảng ngày lọc.',
                'Tổng công nợ tồn hiện hành được tách riêng, tính trên toàn bộ hóa đơn chua_thanh_toan / mot_phan trong phạm vi cơ sở/khóa/lớp, không giới hạn ngày phát hành.',
                'Các giao dịch trực tuyến đang khởi tạo / chờ xác nhận hoặc phiếu đã hủy tuyệt đối không cộng vào Thực thu.',
                'Học viên đăng ký khóa chưa xếp lớp hoặc lớp học trực tuyến giữ nhãn "Chưa xếp cơ sở / Trực tuyến", không tự suy diễn cơ sở.',
            ],
        ];
    }

    /**
     * 3. Báo cáo Hiệu suất lớp (Class Performance Report).
     * Purely objective DB metrics; pre-aggregated JOINs avoid correlated per-row subqueries.
     */
    public static function classPerformance(array $filters, array $actor): array
    {
        $campusId = self::enforceScope($filters, $actor);
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
                COALESCE(enr.si_so_dang_hoc, 0) AS siSoDangHoc,
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
                 SELECT x.lop_id, COUNT(*) AS si_so_dang_hoc
                 FROM lop_hoc_vien x
                 JOIN hoc_vien hv ON hv.id = x.hoc_vien_id
                 WHERE hv.deleted_at IS NULL AND x.trang_thai IN ('dang_hoc','hoc_thu')
                 GROUP BY x.lop_id
             ) enr ON enr.lop_id = l.id
             LEFT JOIN (
                 SELECT b.lop_id,
                        COUNT(*) AS tong_buoi,
                        SUM(CASE WHEN b.trang_thai = 'da_day' THEN 1 ELSE 0 END) AS da_dien_ra,
                        SUM(CASE WHEN b.trang_thai = 'dang_day' THEN 1 ELSE 0 END) AS dang_day,
                        SUM(CASE WHEN b.trang_thai = 'du_kien' THEN 1 ELSE 0 END) AS sap_dien_ra,
                        SUM(CASE WHEN b.trang_thai = 'huy' THEN 1 ELSE 0 END) AS bi_huy,
                        SUM(CASE WHEN b.trang_thai = 'doi' THEN 1 ELSE 0 END) AS doi_lich,
                        SUM(CASE WHEN b.loai = 'bu' AND b.trang_thai NOT IN ('huy','doi') THEN 1 ELSE 0 END) AS hoc_bu,
                        SUM(CASE WHEN s_agg.buoi_hoc_id IS NOT NULL THEN 1 ELSE 0 END) AS co_so_hoc_vu
                 FROM buoi_hoc b
                 LEFT JOIN (SELECT DISTINCT buoi_hoc_id FROM so_theo_doi_buoi_hoc) s_agg ON s_agg.buoi_hoc_id = b.id
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
        $totActiveRun = 0;
        $totUpcoming = 0;
        $totCancel = 0;
        $totRescheduled = 0;
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
            $totActiveRun += (int)$r['soBuoiDangDay'];
            $totUpcoming += (int)$r['soBuoiSapDienRa'];
            $totCancel += (int)$r['soBuoiBiHuy'];
            $totRescheduled += (int)$r['soBuoiDoiLich'];
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

        // Pre-aggregated per-session attendance counts (eliminates 2 correlated subqueries per row)
        $details = Store::rows(
            "SELECT b.id AS buoiHocId, b.ngay, b.gio_bat_dau AS gioBatDau, b.gio_ket_thuc AS gioKetThuc,
                    b.loai AS loaiBuoi, b.trang_thai AS trangThaiBuoi,
                    l.ma_lop AS maLop, l.ten_lop AS tenLop,
                    COALESCE(cs.ten_co_so, IF(l.hinh_thuc='online','Trực tuyến','Chưa gắn cơ sở')) AS tenCoSo,
                    COALESCE(sa.so_luot_diem_danh, 0) AS soLuotDiemDanh,
                    COALESCE(sa.so_co_mat_hoac_tre, 0) AS soCoMatHoacTre
             FROM buoi_hoc b
             JOIN lop_hoc l ON l.id = b.lop_id
             LEFT JOIN co_so cs ON cs.id = COALESCE(b.co_so_id, l.co_so_id)
             LEFT JOIN (
                 SELECT s.buoi_hoc_id,
                        COUNT(*) AS so_luot_diem_danh,
                        SUM(CASE WHEN s.trang_thai_diem_danh IN ('co_mat','di_tre') THEN 1 ELSE 0 END) AS so_co_mat_hoac_tre
                 FROM so_theo_doi_buoi_hoc s
                 JOIN buoi_hoc b2 ON b2.id = s.buoi_hoc_id
                 WHERE b2.ngay BETWEEN ? AND ?
                 GROUP BY s.buoi_hoc_id
             ) sa ON sa.buoi_hoc_id = b.id
             WHERE $whereSql AND b.ngay BETWEEN ? AND ?
             ORDER BY b.ngay DESC, b.gio_bat_dau DESC, l.ma_lop ASC
             LIMIT ".self::DETAIL_LIMIT,
            [$filters['tuNgay'], $filters['denNgay'], ...$params, $filters['tuNgay'], $filters['denNgay']]
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
                'soBuoiDangDay' => $totActiveRun,
                'soBuoiSapDienRa' => $totUpcoming,
                'soBuoiBiHuy' => $totCancel,
                'soBuoiDoiLich' => $totRescheduled,
                'soBuoiHocBu' => $totMakeup,
                'tyLeChuyenCan' => $totMarked > 0 ? round($totPresent * 100 / $totMarked, 1) : null,
            ],
            'classes' => $classes,
            'details' => $details,
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Sĩ số đang học = Số học viên có ghi danh lop_hoc_vien ở trạng thái dang_hoc hoặc hoc_thu (chưa bị xóa mềm).',
                'Tỷ lệ lấp đầy sĩ số (%) = Sĩ số đang học / Sĩ số tối đa × 100.',
                'Số buổi đã diễn ra = Số buổi buoi_hoc trong khoảng ngày có trang_thai = da_day; buổi bị hủy (huy) và đổi lịch (doi) được loại khỏi mẫu số chuyên cần.',
                'Tỷ lệ chuyên cần (%) = Số lượt điểm danh co_mat hoặc di_tre / Tổng lượt đã điểm danh trong khoảng ngày × 100.',
                'Mọi chỉ số là thống kê vận hành trực tiếp từ DB, không sử dụng điểm đánh giá chủ quan hay kết luận lợi nhuận.',
            ],
        ];
    }

    /**
     * 4. Báo cáo Công dạy & Quỹ lương (Teaching Hours & Payroll Report — Admin only).
     * Strictly uses snapshotted shift wage (fn_tien_cong), intersection minutes (so_phut_tinh_cong),
     * and bang_cong_thang status (mo / da_chot / da_tra). Never alters closed/paid data.
     */
    public static function payroll(array $filters, array $actor): array
    {
        $campusId = self::enforceScope($filters, $actor);
        [$fromTs, $toNextTs] = self::dateBounds($filters['tuNgay'], $filters['denNgay']);

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

        // Tuition net actual revenue in matching date/campus/course/class scope for transparent tuition-vs-payroll comparison
        $relJoins = self::invoiceRelationJoins();
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
             LIMIT ".self::DETAIL_LIMIT,
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
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Chỉ tính các ca đã hoàn tất hợp lệ: buoi_hoc.trang_thai = da_day, phan_cong_buoi_hoc.trang_thai = da_day, trang_thai_cong = hop_le.',
                'Phút tính công lấy trực tiếp so_phut_tinh_cong (giao giữa khoảng check-in/out và khung giờ lịch, không cộng giờ ngoài lịch). Số phút đi trễ tính khi checkin_tre = 1 theo chênh lệch checkin_luc − gio_lich_bat_dau.',
                'Tiền công mỗi ca dùng fn_tien_cong(cach_tinh_luong, don_gia, so_phut_tinh_cong) theo đơn giá snapshot gắn trên từng ca đã bắt đầu; không làm thay đổi kỳ đã chốt/đã trả.',
                'Đối chiếu Thực thu ròng − Quỹ lương giảng dạy chỉ so sánh học phí thực thu (phiếu hop_le cùng khoảng ngày/cơ sở/khóa/lớp) với tiền công giảng dạy, chưa tính thưởng/khấu trừ tháng hoặc chi phí vận hành khác — không phải lợi nhuận ròng toàn trung tâm.',
            ],
        ];
    }

    /**
     * 5. Báo cáo Công nợ tồn & Quá hạn (Outstanding & Overdue Debt Report — Admin only).
     * Includes ALL unpaid or partially paid invoices regardless of issue date.
     */
    public static function debt(array $filters, array $actor): array
    {
        $campusId = self::enforceScope($filters, $actor);
        $relJoins = self::invoiceRelationJoins();

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
             LIMIT ".self::DETAIL_LIMIT,
            $params
        );

        return [
            'report' => 'debt',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byGroup' => $byGroup,
            'details' => $details,
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Liệt kê toàn bộ hóa đơn có trang_thai thuộc (chua_thanh_toan, mot_phan) và còn số dư phai_thu − da_thu > 0, kể cả hóa đơn phát hành trước kỳ lọc.',
                'Phân nhóm theo han_thanh_toan so với ngày hiện tại (Asia/Ho_Chi_Minh): Quá hạn (han_thanh_toan < hôm nay), Chưa đến hạn (han_thanh_toan ≥ hôm nay), Chưa đặt hạn (han_thanh_toan NULL).',
                'Sắp xếp ưu tiên hóa đơn có hạn thanh toán sớm nhất / quá hạn lâu nhất lên đầu để hỗ trợ đôn đốc thu phí.',
            ],
        ];
    }

    /**
     * 6. Thống kê Yêu cầu học & Tuyển sinh (Admissions Request Status Report — Admin & Giáo vụ).
     * Strictly reflects current yeu_cau_hoc state (no invented historical stage transition logs).
     */
    public static function admissions(array $filters, array $actor): array
    {
        $campusId = self::enforceScope($filters, $actor);
        [$fromTs, $toNextTs] = self::dateBounds($filters['tuNgay'], $filters['denNgay']);

        $where = ['yc.created_at >= ?', 'yc.created_at < ?'];
        $params = [$fromTs, $toNextTs];

        if ($campusId !== null) {
            $where[] = 'yc.co_so_mong_muon_id = ?';
            $params[] = $campusId;
        }
        if (!empty($filters['monHocId'])) {
            $where[] = 'yc.mon_hoc_id = ?';
            $params[] = (int)$filters['monHocId'];
        }
        if (!empty($filters['trangThai'])) {
            $where[] = 'yc.trang_thai = ?';
            $params[] = $filters['trangThai'];
        }
        $whereSql = implode(' AND ', $where);

        $baseFrom = "FROM yeu_cau_hoc yc
            JOIN mon_hoc m ON m.id = yc.mon_hoc_id
            JOIN cap_lop cl ON cl.id = yc.cap_lop_id
            LEFT JOIN co_so cs ON cs.id = yc.co_so_mong_muon_id
            LEFT JOIN hoc_vien hv ON hv.id = yc.hoc_vien_id AND hv.deleted_at IS NULL
            LEFT JOIN (
                SELECT dx.yeu_cau_id,
                       COUNT(DISTINCT dx.id) AS so_de_xuat,
                       MAX(CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END) AS da_mo_lop
                FROM de_xuat_nhan_su dx
                LEFT JOIN lop_hoc l ON l.de_xuat_id = dx.id AND l.deleted_at IS NULL
                GROUP BY dx.yeu_cau_id
            ) dx_agg ON dx_agg.yeu_cau_id = yc.id";

        $groupRows = Store::rows(
            "SELECT
                COALESCE(cs.id, 0) AS coSoId,
                COALESCE(cs.ten_co_so, IF(yc.hinh_thuc='online','Trực tuyến','Chưa chọn cơ sở')) AS tenCoSo,
                m.id AS monHocId, m.ten AS tenMon,
                COUNT(yc.id) AS tongYeuCau,
                SUM(CASE WHEN yc.trang_thai = 'moi' THEN 1 ELSE 0 END) AS moi,
                SUM(CASE WHEN yc.trang_thai = 'dang_tim' THEN 1 ELSE 0 END) AS dangTim,
                SUM(CASE WHEN yc.trang_thai = 'da_de_xuat' THEN 1 ELSE 0 END) AS daDeXuat,
                SUM(CASE WHEN yc.trang_thai = 'da_chot' THEN 1 ELSE 0 END) AS daChot,
                SUM(CASE WHEN yc.trang_thai = 'huy' THEN 1 ELSE 0 END) AS huy,
                SUM(CASE WHEN COALESCE(dx_agg.da_mo_lop, 0) = 1 THEN 1 ELSE 0 END) AS daMoLop
             $baseFrom
             WHERE $whereSql
             GROUP BY coSoId, tenCoSo, m.id, m.ten
             ORDER BY tongYeuCau DESC, tenCoSo ASC, m.ten ASC",
            $params
        );

        $summary = [
            'tongYeuCau' => 0, 'moi' => 0, 'dangTim' => 0, 'daDeXuat' => 0,
            'daChot' => 0, 'huy' => 0, 'daMoLop' => 0, 'tyLeChot' => null,
        ];
        $byGroup = [];
        foreach ($groupRows as $r) {
            $tot = (int)$r['tongYeuCau'];
            $chot = (int)$r['daChot'];
            $item = [
                'coSoId' => (int)$r['coSoId'],
                'tenCoSo' => $r['tenCoSo'],
                'monHocId' => (int)$r['monHocId'],
                'tenMon' => $r['tenMon'],
                'tongYeuCau' => $tot,
                'moi' => (int)$r['moi'],
                'dangTim' => (int)$r['dangTim'],
                'daDeXuat' => (int)$r['daDeXuat'],
                'daChot' => $chot,
                'huy' => (int)$r['huy'],
                'daMoLop' => (int)$r['daMoLop'],
                'tyLeChot' => $tot > 0 ? round($chot * 100 / $tot, 1) : null,
            ];
            $byGroup[] = $item;
            $summary['tongYeuCau'] += $tot;
            $summary['moi'] += $item['moi'];
            $summary['dangTim'] += $item['dangTim'];
            $summary['daDeXuat'] += $item['daDeXuat'];
            $summary['daChot'] += $chot;
            $summary['huy'] += $item['huy'];
            $summary['daMoLop'] += $item['daMoLop'];
        }
        if ($summary['tongYeuCau'] > 0) {
            $summary['tyLeChot'] = round($summary['daChot'] * 100 / $summary['tongYeuCau'], 1);
        }

        $sourceRows = Store::rows(
            "SELECT yc.nguon,
                    COUNT(yc.id) AS tong,
                    SUM(CASE WHEN yc.trang_thai = 'da_chot' THEN 1 ELSE 0 END) AS daChot,
                    SUM(CASE WHEN yc.trang_thai = 'huy' THEN 1 ELSE 0 END) AS huy
             $baseFrom
             WHERE $whereSql
             GROUP BY yc.nguon
             ORDER BY tong DESC",
            $params
        );
        $bySource = array_map(static function (array $r): array {
            $t = (int)$r['tong'];
            $c = (int)$r['daChot'];
            return [
                'nguon' => $r['nguon'],
                'tong' => $t,
                'daChot' => $c,
                'huy' => (int)$r['huy'],
                'tyLeChot' => $t > 0 ? round($c * 100 / $t, 1) : null,
            ];
        }, $sourceRows);

        $details = Store::rows(
            "SELECT yc.id, yc.ma_yc AS maYc, yc.created_at AS createdAt,
                    COALESCE(hv.ho_ten, yc.ho_ten_lien_he, 'Khách tư vấn') AS khachHang,
                    COALESCE(yc.sdt_lien_he, '') AS sdt,
                    m.ten AS tenMon, cl.ten AS tenCapLop, yc.loai_lop_mong_muon AS loaiLop,
                    COALESCE(cs.ten_co_so, IF(yc.hinh_thuc='online','Trực tuyến','Chưa chọn cơ sở')) AS tenCoSo,
                    yc.nguon, yc.trang_thai AS trangThai,
                    COALESCE(dx_agg.so_de_xuat, 0) AS soDeXuat,
                    COALESCE(dx_agg.da_mo_lop, 0) AS daMoLop
             $baseFrom
             WHERE $whereSql
             ORDER BY yc.created_at DESC, yc.id DESC
             LIMIT ".self::DETAIL_LIMIT,
            $params
        );

        return [
            'report' => 'admissions',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byGroup' => $byGroup,
            'bySource' => $bySource,
            'details' => $details,
            'detailLimit' => self::DETAIL_LIMIT,
            'congThuc' => [
                'Thống kê theo trạng thái hiện tại (moi / dang_tim / da_de_xuat / da_chot / huy) của các yêu cầu học yeu_cau_hoc tạo mới trong khoảng ngày lọc.',
                'Tỷ lệ chốt (%) = Số yêu cầu có trạng thái hiện tại da_chot / Tổng số yêu cầu tạo trong kỳ × 100.',
                'Số yêu cầu đã mở lớp đếm theo liên kết thực tế de_xuat_nhan_su → lop_hoc.de_xuat_id (chưa xóa mềm).',
                'Lưu ý dữ liệu: schema hiện chưa có bảng lịch sử chuyển trạng thái yêu cầu học và chưa có khóa ngoại từ yeu_cau_hoc sang dang_ky_khoa_hoc, nên không ước lượng tỷ lệ rơi giữa từng bước lịch sử.',
            ],
        ];
    }
}
