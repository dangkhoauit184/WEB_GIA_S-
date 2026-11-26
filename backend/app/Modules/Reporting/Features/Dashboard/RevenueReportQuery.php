<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

/**
 * 2. Báo cáo Doanh thu theo khóa học và cơ sở (Revenue Report — Admin only).
 */
final class RevenueReportQuery
{
    public static function run(array $filters, array $actor): array
    {
        $campusId = ReportSqlHelper::enforceScope($filters, $actor);
        [$fromTs, $toNextTs] = ReportSqlHelper::dateBounds($filters['tuNgay'], $filters['denNgay']);
        $relJoins = ReportSqlHelper::invoiceRelationJoins();

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
             LIMIT ".ReportSqlHelper::DETAIL_LIMIT,
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
            'detailLimit' => ReportSqlHelper::DETAIL_LIMIT,
            'congThuc' => [
                'Thực thu ròng = Tổng phiếu thu (loai = thu) − Tổng phiếu hoàn (loai = hoan) của các phiếu thanh_toan có trang_thai = hop_le trong khoảng ngày thanh toán (múi giờ Asia/Ho_Chi_Minh).',
                'Phải thu & Còn nợ trong kỳ tính trên các hóa đơn đã phát hành (khác nhap/huy) trong khoảng ngày lọc.',
                'Tổng công nợ tồn hiện hành được tách riêng, tính trên toàn bộ hóa đơn chua_thanh_toan / mot_phan trong phạm vi cơ sở/khóa/lớp, không giới hạn ngày phát hành.',
                'Các giao dịch trực tuyến đang khởi tạo / chờ xác nhận hoặc phiếu đã hủy tuyệt đối không cộng vào Thực thu.',
                'Học viên đăng ký khóa chưa xếp lớp hoặc lớp học trực tuyến giữ nhãn "Chưa xếp cơ sở / Trực tuyến", không tự suy diễn cơ sở.',
            ],
        ];
    }
}
