<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

/**
 * 3. Báo cáo Hiệu suất lớp (Class Performance Report).
 */
final class ClassPerformanceReportQuery
{
    public static function run(array $filters, array $actor): array
    {
        $campusId = ReportSqlHelper::enforceScope($filters, $actor);
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
             LIMIT ".ReportSqlHelper::DETAIL_LIMIT,
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
            'detailLimit' => ReportSqlHelper::DETAIL_LIMIT,
            'congThuc' => [
                'Sĩ số đang học = Số học viên có ghi danh lop_hoc_vien ở trạng thái dang_hoc hoặc hoc_thu (chưa bị xóa mềm).',
                'Tỷ lệ lấp đầy sĩ số (%) = Sĩ số đang học / Sĩ số tối đa × 100.',
                'Số buổi đã diễn ra = Số buổi buoi_hoc trong khoảng ngày có trang_thai = da_day; buổi bị hủy (huy) và đổi lịch (doi) được loại khỏi mẫu số chuyên cần.',
                'Tỷ lệ chuyên cần (%) = Số lượt điểm danh co_mat hoặc di_tre / Tổng lượt đã điểm danh trong khoảng ngày × 100.',
                'Mọi chỉ số là thống kê vận hành trực tiếp từ DB, không sử dụng điểm đánh giá chủ quan hay kết luận lợi nhuận.',
            ],
        ];
    }
}
