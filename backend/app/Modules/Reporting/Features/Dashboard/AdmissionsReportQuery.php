<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\Store;

/**
 * 6. Thống kê Yêu cầu học & Tuyển sinh (Admissions Request Status Report — Admin & Giáo vụ).
 */
final class AdmissionsReportQuery
{
    public static function run(array $filters, array $actor): array
    {
        $campusId = ReportSqlHelper::enforceScope($filters, $actor);
        [$fromTs, $toNextTs] = ReportSqlHelper::dateBounds($filters['tuNgay'], $filters['denNgay']);

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
             LIMIT ".ReportSqlHelper::DETAIL_LIMIT,
            $params
        );

        return [
            'report' => 'admissions',
            'filters' => $filters + ['coSoIdEffective' => $campusId],
            'summary' => $summary,
            'byGroup' => $byGroup,
            'bySource' => $bySource,
            'details' => $details,
            'detailLimit' => ReportSqlHelper::DETAIL_LIMIT,
            'congThuc' => [
                'Thống kê theo trạng thái hiện tại (moi / dang_tim / da_de_xuat / da_chot / huy) của các yêu cầu học yeu_cau_hoc tạo mới trong khoảng ngày lọc.',
                'Tỷ lệ chốt (%) = Số yêu cầu có trạng thái hiện tại da_chot / Tổng số yêu cầu tạo trong kỳ × 100.',
                'Số yêu cầu đã mở lớp đếm theo liên kết thực tế de_xuat_nhan_su → lop_hoc.de_xuat_id (chưa xóa mềm).',
                'Lưu ý dữ liệu: schema hiện chưa có bảng lịch sử chuyển trạng thái yêu cầu học và chưa có khóa ngoại từ yeu_cau_hoc sang dang_ky_khoa_hoc, nên không ước lượng tỷ lệ rơi giữa từng bước lịch sử.',
            ],
        ];
    }
}
