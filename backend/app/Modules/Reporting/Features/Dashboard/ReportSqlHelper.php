<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\{ApiException, Store};

final class ReportSqlHelper
{
    public const DETAIL_LIMIT = 5000;

    public static function dateBounds(string $tuNgay, string $denNgay): array
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

    public static function invoiceRelationJoins(): string
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
}
