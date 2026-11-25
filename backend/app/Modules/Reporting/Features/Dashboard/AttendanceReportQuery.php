<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

/**
 * 1. Báo cáo Chuyên cần (Attendance Report).
 * Distinguishes: co_mat, di_tre, vang_co_phep, vang_khong_phep, chua_diem_danh, chua_dien_ra.
 */
final class AttendanceReportQuery
{
    public static function run(array $filters, array $actor): array
    {
        $campusId = ReportSqlHelper::enforceScope($filters, $actor);
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
             LIMIT ".ReportSqlHelper::DETAIL_LIMIT,
            $slotParams
        );

        return [
            'report' => 'attendance',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byClass' => $byClass,
            'details' => $details,
            'detailLimit' => ReportSqlHelper::DETAIL_LIMIT,
            'congThuc' => [
                'Tỷ lệ chuyên cần (%) = (Có mặt + Đi trễ) / Tổng lượt đã điểm danh × 100.',
                'Lượt chưa diễn ra (buổi dự kiến trong tương lai) và chưa điểm danh được tách riêng, tuyệt đối không tính vào vắng mặt.',
                'Các buổi đã hủy hoặc đổi lịch không tính vào mẫu số chuyên cần; buổi học bù cá nhân chỉ tính học viên có tên trong hoc_bu_hoc_vien.',
            ],
        ];
    }
}
