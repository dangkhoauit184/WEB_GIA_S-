<?php
namespace App\Modules\Academic\Features\Scheduling;

use App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter;
use App\Shared\{Access, ApiException, Input, Store};

final class ShiftRecruitment
{
    private const OPENING_SELECT = "SELECT d.id, d.ma_de_xuat, d.buoi_hoc_id, d.vai_tro, d.so_luong, d.yeu_cau,
            d.trang_thai, d.nguoi_tao_id, d.dong_boi, d.dong_luc, d.created_at, d.updated_at,
            u_tao.hoten AS ten_nguoi_tao,
            b.lop_id, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, b.loai AS loai_buoi, b.trang_thai AS trang_thai_buoi,
            COALESCE(b.phong_hoc, l.phong_hoc) AS phong_hoc,
            COALESCE(b.co_so_id, l.co_so_id) AS co_so_id,
            l.ma_lop, l.ten_lop, l.loai_lop, l.trang_thai AS trang_thai_lop, l.hinh_thuc, l.dia_diem, l.link_hoc,
            l.mon_hoc_id, l.cap_lop_id, l.khu_vuc_id AS lop_khu_vuc_id,
            m.ten AS ten_mon, cl.ten AS ten_cap_lop,
            cs.ten_co_so, cs.khu_vuc_id AS co_so_khu_vuc_id,
            kv.ten AS ten_khu_vuc,
            (SELECT u_gv.hoten FROM phan_cong_buoi_hoc pc_gv JOIN users u_gv ON u_gv.user_id=pc_gv.user_id
                WHERE pc_gv.buoi_hoc_id=b.id AND pc_gv.vai_tro='day_chinh' AND pc_gv.trang_thai<>'da_go' LIMIT 1) AS ten_nguoi_day_chinh,
            (SELECT GROUP_CONCAT(u_tg.hoten SEPARATOR ', ') FROM phan_cong_buoi_hoc pc_tg JOIN users u_tg ON u_tg.user_id=pc_tg.user_id
                WHERE pc_tg.buoi_hoc_id=b.id AND pc_tg.vai_tro='tro_giang' AND pc_tg.trang_thai<>'da_go') AS ten_tro_giang,
            (SELECT COUNT(*) FROM ung_tuyen_ca uc WHERE uc.de_xuat_id=d.id AND uc.trang_thai='da_duyet') AS so_da_duyet,
            (SELECT COUNT(*) FROM ung_tuyen_ca uc WHERE uc.de_xuat_id=d.id AND uc.trang_thai='cho_duyet') AS so_cho_duyet,
            (SELECT COUNT(*) FROM ung_tuyen_ca uc WHERE uc.de_xuat_id=d.id) AS tong_ung_tuyen
        FROM de_xuat_tuyen_ca d
        JOIN buoi_hoc b ON b.id=d.buoi_hoc_id
        JOIN lop_hoc l ON l.id=b.lop_id
        JOIN mon_hoc m ON m.id=l.mon_hoc_id
        JOIN cap_lop cl ON cl.id=l.cap_lop_id
        JOIN users u_tao ON u_tao.user_id=d.nguoi_tao_id
        LEFT JOIN co_so cs ON cs.id=COALESCE(b.co_so_id, l.co_so_id)
        LEFT JOIN khu_vuc kv ON kv.id=COALESCE(l.khu_vuc_id, cs.khu_vuc_id)
        WHERE l.deleted_at IS NULL";

    private static function text(mixed $value, int $max, bool $required = false): ?string
    {
        if ($value === null && !$required) return null;
        if (!is_string($value)) throw new ApiException(400, 'Nội dung không hợp lệ.');
        $trimmed = trim($value);
        if (($required && $trimmed === '') || mb_strlen($trimmed) > $max) {
            throw new ApiException(400, $required ? 'Vui lòng nhập nội dung hợp lệ (tối đa '.$max.' ký tự).' : 'Nội dung tối đa '.$max.' ký tự.');
        }
        return $trimmed === '' ? null : $trimmed;
    }

    private static function candidateRoleFor(array $user): string
    {
        if (in_array($user['role'], ['GIAO_VIEN', 'GIA_SU'], true)) return 'day_chinh';
        if ($user['role'] === 'TRO_GIANG') return 'tro_giang';
        Access::forbidden();
    }

    private static function profileForUser(int $userId, bool $lock = false): ?array
    {
        return Store::one('SELECT id, user_id, ma_ns, phan_loai, hinh_thuc_day, don_vi_cong_tac, kinh_nghiem_nam, gioi_thieu, trang_thai
            FROM nhan_su_chuyen_mon WHERE user_id=? AND deleted_at IS NULL'.($lock ? ' FOR UPDATE' : ''), [$userId]);
    }

    private static function roleMatchesProfile(string $openingRole, array $profile, ?string $accountRole = null): bool
    {
        $types = array_filter(explode(',', (string)($profile['phan_loai'] ?? '')));
        if ($openingRole === 'day_chinh') {
            if ($accountRole !== null && !in_array($accountRole, ['GIAO_VIEN', 'GIA_SU'], true)) return false;
            return (bool)array_intersect($types, ['giao_vien', 'gia_su']);
        }
        if ($openingRole === 'tro_giang') {
            if ($accountRole !== null && $accountRole !== 'TRO_GIANG') return false;
            return in_array('tro_giang', $types, true);
        }
        return false;
    }

    private static function isPureTutor(array $profile, ?string $accountRole = null): bool
    {
        if ($accountRole === 'GIA_SU') return true;
        $types = array_filter(explode(',', (string)($profile['phan_loai'] ?? '')));
        return in_array('gia_su', $types, true) && !in_array('giao_vien', $types, true);
    }

    private static function evaluateEligibility(array $opening, array $profile, string $now, ?string $accountRole = null): array
    {
        $reasons = [];
        $sessionStart = $opening['ngay'].' '.$opening['gio_bat_dau'];

        if (($opening['trang_thai_buoi'] ?? '') !== 'du_kien' || $sessionStart <= $now) {
            $reasons[] = 'Ca học đã bắt đầu, hoàn tất hoặc bị hủy/đổi lịch.';
        }
        if (!in_array($opening['trang_thai_lop'] ?? '', ['sap_bat_dau', 'dang_hoc'], true)) {
            $reasons[] = 'Lớp học không còn ở trạng thái đang hoạt động.';
        }
        if (($profile['trang_thai'] ?? '') !== 'hoat_dong') {
            $reasons[] = 'Hồ sơ nhân sự hiện không ở trạng thái đang hoạt động.';
        }
        if (!self::roleMatchesProfile($opening['vai_tro'], $profile, $accountRole)) {
            $reasons[] = $opening['vai_tro'] === 'day_chinh'
                ? 'Vị trí này chỉ dành cho Giáo viên / Gia sư.'
                : 'Vị trí này chỉ dành cho Trợ giảng.';
        } elseif ($opening['vai_tro'] === 'day_chinh'
            && self::isPureTutor($profile, $accountRole)
            && ($opening['loai_lop'] ?? '') === 'nhom') {
            $reasons[] = 'Gia sư chỉ được phân công ca thuộc lớp kèm gia sư (1:1 hoặc 1:2).';
        }

        $alreadyAssigned = Store::one("SELECT id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND user_id=? AND trang_thai<>'da_go' LIMIT 1",
            [$opening['buoi_hoc_id'], $profile['user_id']]);
        if ($alreadyAssigned) {
            $reasons[] = 'Bạn đã được phân công trong ca học này.';
        }

        $qualified = Store::one('SELECT id FROM nhan_su_mon WHERE nhan_su_id=? AND mon_hoc_id=? AND ? BETWEEN cap_lop_tu AND cap_lop_den LIMIT 1',
            [$profile['id'], $opening['mon_hoc_id'], $opening['cap_lop_id']]);
        if (!$qualified) {
            $reasons[] = 'Chưa khớp chuyên môn '.$opening['ten_mon'].' · '.$opening['ten_cap_lop'].'.';
        }

        $isOnline = ($opening['hinh_thuc'] ?? '') === 'online'
            || in_array((string)($opening['phong_hoc'] ?? ''), ['Online', 'Google Meet'], true);
        $requiredMode = $isOnline ? 'online' : 'offline';
        $modes = array_filter(explode(',', (string)($profile['hinh_thuc_day'] ?? '')));
        if (!in_array($requiredMode, $modes, true)) {
            $reasons[] = 'Hồ sơ chưa bật hình thức '.($isOnline ? 'Trực tuyến (Online)' : 'Trực tiếp (Offline)').'.';
        }

        if (!$isOnline) {
            $regionId = $opening['lop_khu_vuc_id'] ?? $opening['co_so_khu_vuc_id'] ?? null;
            if ($regionId !== null) {
                $inRegion = Store::one('SELECT 1 AS ok FROM nhan_su_khu_vuc WHERE nhan_su_id=? AND khu_vuc_id=? LIMIT 1',
                    [$profile['id'], $regionId]);
                if (!$inRegion) {
                    $reasons[] = 'Chưa đăng ký khu vực '.($opening['ten_khu_vuc'] ?: 'của lớp học').'.';
                }
            }
        }

        $weekday = (int)(new \DateTimeImmutable($opening['ngay']))->format('N') + 1;
        $available = Store::one('SELECT id FROM nhan_su_lich_ranh WHERE nhan_su_id=? AND thu=? AND gio_bat_dau<=? AND gio_ket_thuc>=? LIMIT 1',
            [$profile['id'], $weekday, $opening['gio_bat_dau'], $opening['gio_ket_thuc']]);
        if (!$available) {
            $dayLabel = $weekday === 8 ? 'Chủ nhật' : 'Thứ '.$weekday;
            $reasons[] = 'Ngoài lịch rảnh đã đăng ký ('.$dayLabel.' '.substr($opening['gio_bat_dau'], 0, 5).'–'.substr($opening['gio_ket_thuc'], 0, 5).').';
        }

        $conflict = Store::one("SELECT b.id, l.ma_lop, b.gio_bat_dau, b.gio_ket_thuc
            FROM phan_cong_buoi_hoc pc
            JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
            JOIN lop_hoc l ON l.id=b.lop_id
            WHERE pc.user_id=? AND pc.trang_thai<>'da_go' AND l.deleted_at IS NULL
              AND b.id<>? AND b.ngay=? AND b.trang_thai NOT IN ('huy','doi')
              AND b.gio_bat_dau<? AND b.gio_ket_thuc>?
            LIMIT 1",
            [$profile['user_id'], $opening['buoi_hoc_id'], $opening['ngay'], $opening['gio_ket_thuc'], $opening['gio_bat_dau']]);
        if ($conflict) {
            $reasons[] = 'Trùng lịch với ca '.$conflict['ma_lop'].' ('.substr($conflict['gio_bat_dau'], 0, 5).'–'.substr($conflict['gio_ket_thuc'], 0, 5).').';
        }

        $lockedPeriod = Store::one("SELECT trang_thai FROM bang_cong_thang WHERE user_id=? AND nam=YEAR(?) AND thang=MONTH(?) AND trang_thai<>'mo' LIMIT 1",
            [$profile['user_id'], $opening['ngay'], $opening['ngay']]);
        if ($lockedPeriod) {
            $reasons[] = 'Kỳ công tháng này đã khóa.';
        }

        return ['duDieuKien' => $reasons === [], 'lyDo' => $reasons];
    }

    private static function mapOpening(array $row): array
    {
        $isOnline = ($row['hinh_thuc'] ?? '') === 'online'
            || in_array((string)($row['phong_hoc'] ?? ''), ['Online', 'Google Meet'], true);
        return [
            'id' => (int)$row['id'],
            'maDeXuat' => $row['ma_de_xuat'],
            'buoiHocId' => (int)$row['buoi_hoc_id'],
            'lopId' => (int)$row['lop_id'],
            'maLop' => $row['ma_lop'],
            'tenLop' => $row['ten_lop'],
            'loaiLop' => $row['loai_lop'],
            'tenMon' => $row['ten_mon'],
            'tenCapLop' => $row['ten_cap_lop'],
            'hinhThuc' => $isOnline ? 'online' : 'offline',
            'tenCoSo' => $row['ten_co_so'],
            'tenKhuVuc' => $row['ten_khu_vuc'],
            'phongHoc' => $row['phong_hoc'],
            'diaDiem' => $row['dia_diem'],
            'ngay' => $row['ngay'],
            'gioBatDau' => $row['gio_bat_dau'],
            'gioKetThuc' => $row['gio_ket_thuc'],
            'loaiBuoi' => $row['loai_buoi'],
            'trangThaiBuoi' => $row['trang_thai_buoi'],
            'tenNguoiDayChinh' => $row['ten_nguoi_day_chinh'],
            'tenTroGiang' => $row['ten_tro_giang'],
            'vaiTro' => $row['vai_tro'],
            'soLuong' => (int)$row['so_luong'],
            'soDaDuyet' => (int)$row['so_da_duyet'],
            'soChoDuyet' => (int)$row['so_cho_duyet'],
            'tongUngTuyen' => (int)$row['tong_ung_tuyen'],
            'yeuCau' => $row['yeu_cau'],
            'trangThai' => $row['trang_thai'],
            'tenNguoiTao' => $row['ten_nguoi_tao'],
            'dongLuc' => $row['dong_luc'],
            'createdAt' => $row['created_at'],
        ];
    }

    private static function mapApplication(array $row, ?array $eligibility = null): array
    {
        $out = [
            'id' => (int)$row['id'],
            'deXuatId' => (int)$row['de_xuat_id'],
            'nhanSuId' => (int)$row['nhan_su_id'],
            'userId' => (int)$row['user_id'],
            'maNs' => $row['ma_ns'] ?? null,
            'hoTen' => $row['ho_ten'] ?? null,
            'donViCongTac' => $row['don_vi_cong_tac'] ?? null,
            'kinhNghiemNam' => isset($row['kinh_nghiem_nam']) ? (int)$row['kinh_nghiem_nam'] : null,
            'gioiThieu' => $row['gioi_thieu'] ?? null,
            'ghiChu' => $row['ghi_chu'],
            'trangThai' => $row['trang_thai'],
            'lyDoTuChoi' => $row['ly_do_tu_choi'],
            'nguoiDuyetTen' => $row['ten_nguoi_duyet'] ?? null,
            'duyetLuc' => $row['duyet_luc'],
            'phanCongId' => $row['phan_cong_id'] !== null ? (int)$row['phan_cong_id'] : null,
            'createdAt' => $row['created_at'],
        ];
        if ($eligibility !== null) {
            $out['duDieuKien'] = $eligibility['duDieuKien'];
            $out['lyDoKhongDuDieuKien'] = $eligibility['lyDo'];
        }
        return $out;
    }

    private static function openingRow(int $id, bool $lock = false): array
    {
        $row = Store::one(self::OPENING_SELECT.' AND d.id=?'.($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$row) throw new ApiException(404, 'Không tìm thấy đề xuất tuyển nhân sự.');
        return $row;
    }

    public static function list(array $query): array
    {
        $user = Access::user();
        $now = Store::one('SELECT NOW() AS now')['now'];

        if (Access::staff($user)) {
            $actor = AcademicAffairsFilter::check('VanHanhBuoiHoc', 'view');
            if (array_diff(array_keys($query), ['trangThai', 'vaiTro', 'lopId', 'q'])) Input::fail('fields');
            $filters = Input::validate($query, [
                'trangThai' => [['dang_mo', 'da_du_nguoi', 'da_dong'], false],
                'vaiTro' => [['day_chinh', 'tro_giang'], false],
                'lopId' => ['id', false],
                'q' => ['string', false],
            ]);
            [$scope, $params] = Access::classScope($actor, 'l');
            $sql = self::OPENING_SELECT.$scope;
            if (!empty($filters['trangThai'])) { $sql .= ' AND d.trang_thai=?'; $params[] = $filters['trangThai']; }
            if (!empty($filters['vaiTro'])) { $sql .= ' AND d.vai_tro=?'; $params[] = $filters['vaiTro']; }
            if (!empty($filters['lopId'])) { $sql .= ' AND b.lop_id=?'; $params[] = $filters['lopId']; }
            if (!empty($filters['q'])) {
                $sql .= ' AND (d.ma_de_xuat LIKE ? OR l.ma_lop LIKE ? OR l.ten_lop LIKE ? OR m.ten LIKE ?)';
                array_push($params, ...array_fill(0, 4, '%'.$filters['q'].'%'));
            }
            $rows = Store::rows($sql.' ORDER BY FIELD(d.trang_thai,\'dang_mo\',\'da_du_nguoi\',\'da_dong\'), b.ngay ASC, b.gio_bat_dau ASC, d.id DESC', $params);
            $openingIds = array_map(static fn($r) => (int)$r['id'], $rows);
            $appsByOpening = [];
            if ($openingIds) {
                $in = implode(',', array_fill(0, count($openingIds), '?'));
                $apps = Store::rows("SELECT uc.*, ns.ma_ns, ns.phan_loai, ns.hinh_thuc_day, ns.don_vi_cong_tac,
                        ns.kinh_nghiem_nam, ns.gioi_thieu, ns.trang_thai AS ns_trang_thai,
                        u.hoten AS ho_ten, u_d.hoten AS ten_nguoi_duyet,
                        g.group_name
                    FROM ung_tuyen_ca uc
                    JOIN nhan_su_chuyen_mon ns ON ns.id=uc.nhan_su_id
                    JOIN users u ON u.user_id=uc.user_id
                    LEFT JOIN account a ON a.user_id=uc.user_id AND a.is_deleted=0
                    LEFT JOIN role_group g ON g.group_id=a.group_id
                    LEFT JOIN users u_d ON u_d.user_id=uc.nguoi_duyet_id
                    WHERE uc.de_xuat_id IN ($in)
                    ORDER BY FIELD(uc.trang_thai,'cho_duyet','da_duyet','tu_choi','da_huy'), uc.created_at ASC, uc.id ASC", $openingIds);
                $openMap = [];
                foreach ($rows as $r) $openMap[(int)$r['id']] = $r;
                foreach ($apps as $app) {
                    $oid = (int)$app['de_xuat_id'];
                    $elig = null;
                    if ($app['trang_thai'] === 'cho_duyet') {
                        $accRole = $app['group_name'] ? Access::role($app['group_name'], (string)$app['phan_loai']) : null;
                        $elig = self::evaluateEligibility($openMap[$oid], [
                            'id' => (int)$app['nhan_su_id'],
                            'user_id' => (int)$app['user_id'],
                            'phan_loai' => $app['phan_loai'],
                            'hinh_thuc_day' => $app['hinh_thuc_day'],
                            'trang_thai' => $app['ns_trang_thai'],
                        ], $now, $accRole);
                    }
                    $appsByOpening[$oid][] = self::mapApplication($app, $elig);
                }
            }
            $result = [];
            foreach ($rows as $row) {
                $mapped = self::mapOpening($row);
                $mapped['ungTuyens'] = $appsByOpening[$mapped['id']] ?? [];
                $result[] = $mapped;
            }
            return $result;
        }

        // Candidate view: Teacher / Tutor (day_chinh ONLY; GIA_SU restricted to kem_1_1/kem_1_2) or Assistant (tro_giang ONLY)
        $roleNeeded = self::candidateRoleFor($user);
        $profile = self::profileForUser((int)$user['userId']);
        if (!$profile) {
            throw new ApiException(404, 'Chưa có hồ sơ chuyên môn.');
        }

        $tutorFilter = self::isPureTutor($profile, $user['role']) ? " AND l.loai_lop IN ('kem_1_1','kem_1_2')" : '';
        $rows = Store::rows(self::OPENING_SELECT." AND d.vai_tro=?
            AND (
                (d.trang_thai='dang_mo' AND b.trang_thai='du_kien' AND TIMESTAMP(b.ngay,b.gio_bat_dau)>NOW() AND l.trang_thai IN ('sap_bat_dau','dang_hoc')$tutorFilter)
                OR EXISTS (SELECT 1 FROM ung_tuyen_ca mine WHERE mine.de_xuat_id=d.id AND mine.user_id=?)
            )
            ORDER BY FIELD(d.trang_thai,'dang_mo','da_du_nguoi','da_dong'), b.ngay ASC, b.gio_bat_dau ASC, d.id DESC",
            [$roleNeeded, (int)$user['userId']]);

        $myApps = [];
        if ($rows) {
            $ids = array_map(static fn($r) => (int)$r['id'], $rows);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $appRows = Store::rows("SELECT uc.*, u_d.hoten AS ten_nguoi_duyet
                FROM ung_tuyen_ca uc
                LEFT JOIN users u_d ON u_d.user_id=uc.nguoi_duyet_id
                WHERE uc.user_id=? AND uc.de_xuat_id IN ($in)",
                [(int)$user['userId'], ...$ids]);
            foreach ($appRows as $app) {
                $myApps[(int)$app['de_xuat_id']] = self::mapApplication($app);
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $mapped = self::mapOpening($row);
            $mine = $myApps[$mapped['id']] ?? null;
            $elig = $mapped['trangThai'] === 'dang_mo' && $mine === null
                ? self::evaluateEligibility($row, $profile, $now, $user['role'])
                : ['duDieuKien' => false, 'lyDo' => []];
            $mapped['donCuaToi'] = $mine;
            $mapped['duDieuKien'] = $mine === null && $mapped['trangThai'] === 'dang_mo' && $elig['duDieuKien'];
            $mapped['lyDoKhongDuDieuKien'] = $mine === null && $mapped['trangThai'] === 'dang_mo' ? $elig['lyDo'] : [];
            $result[] = $mapped;
        }
        return $result;
    }

    public static function create(array $body): array
    {
        $actor = AcademicAffairsFilter::check('VanHanhBuoiHoc', 'add');
        if (array_diff(array_keys($body), ['buoiHocId', 'vaiTro', 'soLuong', 'yeuCau'])) Input::fail('fields');
        $data = Input::validate($body, [
            'buoiHocId' => ['id', true],
            'vaiTro' => [['day_chinh', 'tro_giang'], true],
            'soLuong' => ['integer', false, 1],
        ]);
        $soLuong = (int)($data['soLuong'] ?? 1);
        if ($soLuong < 1 || $soLuong > 5 || ($data['vaiTro'] === 'day_chinh' && $soLuong !== 1)) {
            throw new ApiException(400, $data['vaiTro'] === 'day_chinh'
                ? 'Vị trí Giáo viên chính chỉ tuyển tối đa 1 người cho mỗi ca.'
                : 'Số lượng cần tuyển phải từ 1 đến 5.');
        }
        $yeuCau = self::text($body['yeuCau'] ?? null, 500);

        $openingId = Store::transaction(function () use ($actor, $data, $soLuong, $yeuCau) {
            $session = Store::one("SELECT b.*, l.ma_lop, l.ten_lop, l.loai_lop, l.trang_thai AS trang_thai_lop, l.deleted_at AS lop_deleted_at,
                    l.mon_hoc_id, l.cap_lop_id, l.hinh_thuc, l.khu_vuc_id AS lop_khu_vuc_id, l.co_so_id AS lop_co_so_id,
                    (TIMESTAMP(b.ngay, b.gio_bat_dau) > NOW()) AS chua_bat_dau
                FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id WHERE b.id=? FOR UPDATE", [$data['buoiHocId']]);
            if (!$session || $session['lop_deleted_at'] !== null) {
                throw new ApiException(404, 'Không tìm thấy ca học.');
            }
            Access::classAllowed((int)$session['lop_id'], $actor);
            if ($actor['role'] === 'GIAO_VU' && $actor['coSoId'] && $session['co_so_id'] && (int)$session['co_so_id'] !== (int)$actor['coSoId']) {
                throw new ApiException(403, 'Cơ sở của ca học nằm ngoài phạm vi phụ trách.');
            }
            if (!in_array($session['trang_thai_lop'], ['sap_bat_dau', 'dang_hoc'], true)) {
                throw new ApiException(409, 'Lớp học không còn ở trạng thái đang hoạt động.');
            }
            if ($session['trang_thai'] !== 'du_kien' || !(int)$session['chua_bat_dau']) {
                throw new ApiException(409, 'Chỉ tạo đề xuất tuyển nhân sự cho ca học sắp diễn ra.');
            }
            if ($data['vaiTro'] === 'day_chinh') {
                $lockedMain = Store::one("SELECT id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND vai_tro='day_chinh' AND trang_thai<>'da_go' AND (checkin_luc IS NOT NULL OR bang_cong_id IS NOT NULL) LIMIT 1",
                    [$data['buoiHocId']]);
                if ($lockedMain) {
                    throw new ApiException(409, 'Ca học đã có giáo viên chính check-in hoặc chốt công.');
                }
            }
            $dup = Store::one("SELECT id FROM de_xuat_tuyen_ca WHERE buoi_hoc_id=? AND vai_tro=? AND trang_thai='dang_mo' LIMIT 1",
                [$data['buoiHocId'], $data['vaiTro']]);
            if ($dup) {
                throw new ApiException(409, 'Ca học này đã có đề xuất tuyển cùng vị trí đang mở.');
            }

            $code = 'TC-'.strtoupper(bin2hex(random_bytes(5)));
            $id = Store::insert('de_xuat_tuyen_ca', [
                'ma_de_xuat' => $code,
                'buoi_hoc_id' => $data['buoiHocId'],
                'vai_tro' => $data['vaiTro'],
                'so_luong' => $soLuong,
                'yeu_cau' => $yeuCau,
                'trang_thai' => 'dang_mo',
                'nguoi_tao_id' => $actor['userId'],
            ]);
            $after = Store::one('SELECT * FROM de_xuat_tuyen_ca WHERE id=?', [$id]);
            Store::recordAudit((int)$actor['userId'], 'them', 'de_xuat_tuyen_ca', $id, null, $after);

            // Notify active personnel matching role & subject/grade (bounded, deduped); do not notify pure tutors for group classes
            $roleCond = $data['vaiTro'] === 'day_chinh'
                ? (($session['loai_lop'] ?? '') === 'nhom'
                    ? "FIND_IN_SET('giao_vien', ns.phan_loai)>0 AND g.group_name='GiangVien'"
                    : "(FIND_IN_SET('giao_vien', ns.phan_loai)>0 OR FIND_IN_SET('gia_su', ns.phan_loai)>0) AND g.group_name='GiangVien'")
                : "FIND_IN_SET('tro_giang', ns.phan_loai)>0 AND g.group_name='TroGiang'";
            $candidates = Store::rows("SELECT DISTINCT ns.user_id
                FROM nhan_su_chuyen_mon ns
                JOIN account a ON a.user_id=ns.user_id AND a.status='active' AND a.is_deleted=0
                JOIN role_group g ON g.group_id=a.group_id AND g.is_deleted=0
                JOIN nhan_su_mon nsm ON nsm.nhan_su_id=ns.id AND nsm.mon_hoc_id=? AND ? BETWEEN nsm.cap_lop_tu AND nsm.cap_lop_den
                WHERE ns.deleted_at IS NULL AND ns.trang_thai='hoat_dong' AND $roleCond
                  AND NOT EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc WHERE pc.buoi_hoc_id=? AND pc.user_id=ns.user_id AND pc.trang_thai<>'da_go')
                LIMIT 50",
                [$session['mon_hoc_id'], $session['cap_lop_id'], $data['buoiHocId']]);
            $roleLabel = $data['vaiTro'] === 'day_chinh'
                ? (in_array($session['loai_lop'] ?? '', ['kem_1_1', 'kem_1_2'], true) ? 'Gia sư / Giáo viên' : 'Giáo viên')
                : 'Trợ giảng';
            foreach ($candidates as $cand) {
                Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
                    VALUES (?,'de_xuat',?,?,'de_xuat_tuyen_ca',?,?)", [
                    (int)$cand['user_id'],
                    'Đề xuất tuyển '.$roleLabel.' · '.$session['ma_lop'],
                    $code.' · '.$session['ngay'].' '.substr($session['gio_bat_dau'], 0, 5).'–'.substr($session['gio_ket_thuc'], 0, 5).($yeuCau ? ' · '.$yeuCau : ''),
                    $id,
                    'opening-open:'.$id.':'.$cand['user_id'],
                ]);
            }
            return $id;
        });

        $mapped = self::mapOpening(self::openingRow($openingId));
        $mapped['ungTuyens'] = [];
        return $mapped;
    }

    public static function close(int $id, array $body): array
    {
        $actor = AcademicAffairsFilter::check('VanHanhBuoiHoc', 'edit');
        if ($body !== []) Input::fail('fields');
        Store::transaction(function () use ($id, $actor) {
            $opening = self::openingRow($id, true);
            Access::classAllowed((int)$opening['lop_id'], $actor);
            if ($opening['trang_thai'] !== 'dang_mo') {
                throw new ApiException(409, 'Đề xuất không còn ở trạng thái đang mở.');
            }
            Store::execute("UPDATE de_xuat_tuyen_ca SET trang_thai='da_dong', dong_boi=?, dong_luc=NOW() WHERE id=?",
                [$actor['userId'], $id]);
            $pending = Store::rows("SELECT id, user_id FROM ung_tuyen_ca WHERE de_xuat_id=? AND trang_thai='cho_duyet' FOR UPDATE", [$id]);
            foreach ($pending as $app) {
                Store::execute("UPDATE ung_tuyen_ca SET trang_thai='tu_choi', nguoi_duyet_id=?, duyet_luc=NOW(), ly_do_tu_choi='Đề xuất tuyển nhân sự đã đóng.' WHERE id=?",
                    [$actor['userId'], $app['id']]);
                Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung,co_gui_email)
                    VALUES (?,'duyet_don',?,?,'ung_tuyen_ca',?,?,1)", [
                    (int)$app['user_id'],
                    'Đề xuất tuyển ca đã đóng · '.$opening['ma_lop'],
                    $opening['ma_de_xuat'].' · Đề xuất tuyển nhân sự đã đóng.',
                    (int)$app['id'],
                    'opening-closed-app:'.$app['id'],
                ]);
            }
            Store::recordAudit((int)$actor['userId'], 'sua', 'de_xuat_tuyen_ca', $id, $opening, ['trang_thai' => 'da_dong']);
        });
        return self::mapOpening(self::openingRow($id));
    }

    public static function apply(int $id, array $body): array
    {
        $user = Access::user();
        $roleNeeded = self::candidateRoleFor($user);
        if (array_diff(array_keys($body), ['ghiChu'])) Input::fail('fields');
        $ghiChu = self::text($body['ghiChu'] ?? null, 500);

        $appId = Store::transaction(function () use ($id, $user, $roleNeeded, $ghiChu) {
            $opening = self::openingRow($id, true);
            Store::one('SELECT id FROM buoi_hoc WHERE id=? FOR UPDATE', [$opening['buoi_hoc_id']]);
            Store::one('SELECT user_id FROM users WHERE user_id=? FOR UPDATE', [$user['userId']]);
            $profile = self::profileForUser((int)$user['userId'], true);
            if (!$profile) throw new ApiException(404, 'Chưa có hồ sơ chuyên môn.');

            if ($opening['vai_tro'] !== $roleNeeded) {
                throw new ApiException(403, $opening['vai_tro'] === 'day_chinh'
                    ? 'Vị trí này chỉ dành cho Giáo viên / Gia sư.'
                    : 'Vị trí này chỉ dành cho Trợ giảng.', 'FORBIDDEN');
            }
            if ($opening['vai_tro'] === 'day_chinh'
                && self::isPureTutor($profile, $user['role'])
                && ($opening['loai_lop'] ?? '') === 'nhom') {
                throw new ApiException(403, 'Gia sư chỉ được ứng tuyển ca thuộc lớp kèm gia sư (1:1 hoặc 1:2).', 'FORBIDDEN');
            }
            if ($opening['trang_thai'] !== 'dang_mo') {
                throw new ApiException(409, 'Đề xuất tuyển nhân sự đã đóng hoặc đã đủ người.');
            }

            $existing = Store::one('SELECT id, trang_thai FROM ung_tuyen_ca WHERE de_xuat_id=? AND user_id=? FOR UPDATE',
                [$id, $user['userId']]);
            if ($existing) {
                throw new ApiException(409, 'Bạn đã gửi đơn ứng tuyển cho đề xuất này rồi.');
            }

            $now = Store::one('SELECT NOW() AS now')['now'];
            $elig = self::evaluateEligibility($opening, $profile, $now, $user['role']);
            if (!$elig['duDieuKien']) {
                throw new ApiException(409, implode(' ', $elig['lyDo']));
            }

            $newId = Store::insert('ung_tuyen_ca', [
                'de_xuat_id' => $id,
                'nhan_su_id' => $profile['id'],
                'user_id' => $user['userId'],
                'ghi_chu' => $ghiChu,
                'trang_thai' => 'cho_duyet',
            ]);
            Store::recordAudit((int)$user['userId'], 'them', 'ung_tuyen_ca', $newId, null, [
                'de_xuat_id' => $id,
                'buoi_hoc_id' => (int)$opening['buoi_hoc_id'],
                'vai_tro' => $opening['vai_tro'],
                'ghi_chu' => $ghiChu,
            ]);

            // Notify opening creator & active branch staff
            $staff = Store::rows("SELECT DISTINCT a.user_id FROM account a
                JOIN role_group g ON g.group_id=a.group_id
                LEFT JOIN nhan_vien nv ON nv.user_id=a.user_id
                WHERE a.status='active' AND a.is_deleted=0 AND g.is_deleted=0
                  AND (a.user_id=? OR (g.group_name='NhanVien' AND nv.trang_thai='dang_lam' AND (nv.co_so_id IS NULL OR ? IS NULL OR nv.co_so_id=?)))",
                [$opening['nguoi_tao_id'], $opening['co_so_id'], $opening['co_so_id']]);
            $roleLabel = $opening['vai_tro'] === 'day_chinh'
                ? ($user['role'] === 'GIA_SU' ? 'Gia sư' : 'Giáo viên')
                : 'Trợ giảng';
            foreach ($staff as $recipient) {
                Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
                    VALUES (?,'de_xuat',?,?,'ung_tuyen_ca',?,?)", [
                    (int)$recipient['user_id'],
                    'Ứng tuyển '.$roleLabel.' mới · '.$opening['ma_lop'],
                    ($user['hoTen'] ?? $user['username']).' ứng tuyển '.$opening['ma_de_xuat'].' ('.$opening['ngay'].' '.substr($opening['gio_bat_dau'], 0, 5).'–'.substr($opening['gio_ket_thuc'], 0, 5).').',
                    $newId,
                    'opening-apply:'.$newId.':'.$recipient['user_id'],
                ]);
            }

            return $newId;
        });

        $row = Store::one('SELECT uc.*, ns.ma_ns, ns.don_vi_cong_tac, ns.kinh_nghiem_nam, ns.gioi_thieu, u.hoten AS ho_ten
            FROM ung_tuyen_ca uc JOIN nhan_su_chuyen_mon ns ON ns.id=uc.nhan_su_id JOIN users u ON u.user_id=uc.user_id WHERE uc.id=?', [$appId]);
        return self::mapApplication($row);
    }

    public static function review(int $appId, array $body): array
    {
        $actor = AcademicAffairsFilter::check('VanHanhBuoiHoc', 'edit');
        if (array_diff(array_keys($body), ['trangThai', 'lyDoTuChoi'])) Input::fail('fields');
        $data = Input::validate($body, [
            'trangThai' => [['da_duyet', 'tu_choi'], true],
        ]);
        $reason = self::text($body['lyDoTuChoi'] ?? null, 255, $data['trangThai'] === 'tu_choi');
        if ($data['trangThai'] === 'da_duyet' && $reason !== null) {
            throw new ApiException(400, 'Không nhập lý do từ chối khi duyệt ứng tuyển.');
        }

        Store::transaction(function () use ($appId, $actor, $data, $reason) {
            $appIdentity = Store::one('SELECT id, de_xuat_id, user_id, nhan_su_id FROM ung_tuyen_ca WHERE id=?', [$appId]);
            if (!$appIdentity) throw new ApiException(404, 'Không tìm thấy đơn ứng tuyển.');

            // Deterministic lock order: opening -> session -> application -> applicant user & profile
            $opening = self::openingRow((int)$appIdentity['de_xuat_id'], true);
            Store::one('SELECT id FROM buoi_hoc WHERE id=? FOR UPDATE', [$opening['buoi_hoc_id']]);
            $app = Store::one('SELECT * FROM ung_tuyen_ca WHERE id=? FOR UPDATE', [$appId]);
            Store::one('SELECT user_id FROM users WHERE user_id=? FOR UPDATE', [$app['user_id']]);
            $profile = self::profileForUser((int)$app['user_id'], true);
            Access::classAllowed((int)$opening['lop_id'], $actor);

            if ($app['trang_thai'] !== 'cho_duyet') {
                throw new ApiException(409, 'Đơn ứng tuyển này đã được xử lý trước đó.');
            }

            if ($data['trangThai'] === 'tu_choi') {
                Store::execute("UPDATE ung_tuyen_ca SET trang_thai='tu_choi', nguoi_duyet_id=?, duyet_luc=NOW(), ly_do_tu_choi=? WHERE id=?",
                    [$actor['userId'], $reason, $appId]);
                $after = Store::one('SELECT * FROM ung_tuyen_ca WHERE id=?', [$appId]);
                Store::recordAudit((int)$actor['userId'], 'duyet', 'ung_tuyen_ca', $appId, $app, $after);
                Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung,co_gui_email)
                    VALUES (?,'duyet_don',?,?,'ung_tuyen_ca',?,?,1)", [
                    (int)$app['user_id'],
                    'Đơn ứng tuyển chưa được duyệt · '.$opening['ma_lop'],
                    $opening['ma_de_xuat'].' ('.$opening['ngay'].' '.substr($opening['gio_bat_dau'], 0, 5).'–'.substr($opening['gio_ket_thuc'], 0, 5).') · Lý do: '.$reason,
                    $appId,
                    'opening-reject:'.$appId,
                ]);
                return;
            }

            // Approve path: re-validate EVERYTHING on server, assign official phan_cong_buoi_hoc without modifying buoi_hoc/lop_hoc schedule
            if ($opening['trang_thai'] !== 'dang_mo') {
                throw new ApiException(409, 'Đề xuất đã đóng hoặc đã tuyển đủ nhân sự.');
            }
            $approvedCount = (int)Store::one("SELECT COUNT(*) AS n FROM ung_tuyen_ca WHERE de_xuat_id=? AND trang_thai='da_duyet'", [$opening['id']])['n'];
            if ($approvedCount >= (int)$opening['so_luong']) {
                throw new ApiException(409, 'Đề xuất đã tuyển đủ số lượng nhân sự.');
            }
            if (!$profile) {
                throw new ApiException(409, 'Hồ sơ nhân sự của ứng viên không còn tồn tại.');
            }
            $account = Store::one("SELECT g.group_name FROM account a JOIN role_group g ON g.group_id=a.group_id WHERE a.user_id=? AND a.status='active' AND a.is_deleted=0 AND g.is_deleted=0 LIMIT 1",
                [$app['user_id']]);
            if (!$account) {
                throw new ApiException(409, 'Tài khoản ứng viên không còn hoạt động.');
            }
            $accRole = Access::role($account['group_name'], (string)$profile['phan_loai']);
            $now = Store::one('SELECT NOW() AS now')['now'];
            $elig = self::evaluateEligibility($opening, $profile, $now, $accRole);
            if (!$elig['duDieuKien']) {
                throw new ApiException(409, 'Không thể duyệt do ứng viên không còn đủ điều kiện: '.implode(' ', $elig['lyDo']));
            }

            if ($opening['vai_tro'] === 'day_chinh') {
                $existingMain = Store::one("SELECT * FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND vai_tro='day_chinh' AND trang_thai<>'da_go' FOR UPDATE",
                    [$opening['buoi_hoc_id']]);
                if ($existingMain) {
                    if ($existingMain['checkin_luc'] !== null || $existingMain['bang_cong_id'] !== null) {
                        throw new ApiException(409, 'Ca học đã có giáo viên chính check-in hoặc chốt công, không thể thay phân công.');
                    }
                    Store::execute("UPDATE phan_cong_buoi_hoc SET trang_thai='da_go', go_boi=?, go_luc=NOW(), ly_do_go=? WHERE id=?", [
                        $actor['userId'],
                        'Thay thế qua duyệt ứng tuyển '.$opening['ma_de_xuat'],
                        $existingMain['id'],
                    ]);
                    Store::recordAudit((int)$actor['userId'], 'sua', 'phan_cong_buoi_hoc', (int)$existingMain['id'], $existingMain, [
                        'trang_thai' => 'da_go',
                        'ly_do_go' => 'Thay thế qua duyệt ứng tuyển '.$opening['ma_de_xuat'],
                    ]);
                    Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung,co_gui_email)
                        VALUES (?,'go_phan_cong',?,?,'buoi_hoc',?,?,1)", [
                        (int)$existingMain['user_id'],
                        'Cập nhật phân công ca · '.$opening['ma_lop'],
                        'Ca ngày '.$opening['ngay'].' '.substr($opening['gio_bat_dau'], 0, 5).'–'.substr($opening['gio_ket_thuc'], 0, 5).' đã được điều phối sang giáo viên khác qua đề xuất '.$opening['ma_de_xuat'].'.',
                        (int)$opening['buoi_hoc_id'],
                        'opening-replace:'.$opening['id'].':'.$existingMain['id'],
                    ]);
                }
            }

            // Create or reactivate assignment (DB trigger trg_pc_bi/trg_pc_bu enforces no conflict, open period & auto-snapshots don_gia)
            $oldPc = Store::one('SELECT * FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND user_id=? FOR UPDATE',
                [$opening['buoi_hoc_id'], $app['user_id']]);
            if ($oldPc) {
                if ($oldPc['trang_thai'] !== 'da_go' || $oldPc['vai_tro'] !== $opening['vai_tro']) {
                    throw new ApiException(409, 'Ứng viên đã có phân công trong ca học này.');
                }
                Store::execute("UPDATE phan_cong_buoi_hoc SET trang_thai='da_phan_cong', phan_cong_boi=?, go_boi=NULL, go_luc=NULL, ly_do_go=NULL WHERE id=?",
                    [$actor['userId'], $oldPc['id']]);
                $assignmentId = (int)$oldPc['id'];
            } else {
                $assignmentId = Store::insert('phan_cong_buoi_hoc', [
                    'buoi_hoc_id' => $opening['buoi_hoc_id'],
                    'user_id' => $app['user_id'],
                    'vai_tro' => $opening['vai_tro'],
                    'don_gia' => 0,
                    'cach_tinh_luong' => 'theo_gio',
                    'phan_cong_boi' => $actor['userId'],
                ]);
            }

            Store::execute("UPDATE ung_tuyen_ca SET trang_thai='da_duyet', nguoi_duyet_id=?, duyet_luc=NOW(), phan_cong_id=? WHERE id=?",
                [$actor['userId'], $assignmentId, $appId]);

            if ($approvedCount + 1 >= (int)$opening['so_luong']) {
                Store::execute("UPDATE de_xuat_tuyen_ca SET trang_thai='da_du_nguoi', dong_boi=?, dong_luc=NOW() WHERE id=?",
                    [$actor['userId'], $opening['id']]);
                $others = Store::rows("SELECT id, user_id FROM ung_tuyen_ca WHERE de_xuat_id=? AND id<>? AND trang_thai='cho_duyet' FOR UPDATE",
                    [$opening['id'], $appId]);
                foreach ($others as $other) {
                    Store::execute("UPDATE ung_tuyen_ca SET trang_thai='tu_choi', nguoi_duyet_id=?, duyet_luc=NOW(), ly_do_tu_choi='Vị trí đã tuyển đủ nhân sự.' WHERE id=?",
                        [$actor['userId'], $other['id']]);
                    Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung,co_gui_email)
                        VALUES (?,'duyet_don',?,?,'ung_tuyen_ca',?,?,1)", [
                        (int)$other['user_id'],
                        'Đơn ứng tuyển chưa được duyệt · '.$opening['ma_lop'],
                        $opening['ma_de_xuat'].' · Vị trí đã tuyển đủ nhân sự.',
                        (int)$other['id'],
                        'opening-filled-reject:'.$other['id'],
                    ]);
                }
            }

            $afterApp = Store::one('SELECT * FROM ung_tuyen_ca WHERE id=?', [$appId]);
            Store::recordAudit((int)$actor['userId'], 'duyet', 'ung_tuyen_ca', $appId, $app, $afterApp);
            Store::recordAudit((int)$actor['userId'], 'them', 'phan_cong_buoi_hoc', $assignmentId, $oldPc, [
                'buoi_hoc_id' => (int)$opening['buoi_hoc_id'],
                'user_id' => (int)$app['user_id'],
                'vai_tro' => $opening['vai_tro'],
                'nguon' => $opening['ma_de_xuat'],
            ]);

            $roleLabel = $opening['vai_tro'] === 'day_chinh'
                ? ($accRole === 'GIA_SU' ? 'Gia sư chính' : 'Giáo viên chính')
                : 'Trợ giảng';
            Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung,co_gui_email)
                VALUES (?,'phan_cong',?,?,'buoi_hoc',?,?,1)", [
                (int)$app['user_id'],
                'Đã duyệt phân công '.$roleLabel.' · '.$opening['ma_lop'],
                'Ca ngày '.$opening['ngay'].' '.substr($opening['gio_bat_dau'], 0, 5).'–'.substr($opening['gio_ket_thuc'], 0, 5).' đã được thêm vào thời khóa biểu cá nhân của bạn.',
                (int)$opening['buoi_hoc_id'],
                'opening-approved:'.$appId,
            ]);
        });

        $row = Store::one('SELECT uc.*, ns.ma_ns, ns.don_vi_cong_tac, ns.kinh_nghiem_nam, ns.gioi_thieu,
                u.hoten AS ho_ten, u_d.hoten AS ten_nguoi_duyet
            FROM ung_tuyen_ca uc
            JOIN nhan_su_chuyen_mon ns ON ns.id=uc.nhan_su_id
            JOIN users u ON u.user_id=uc.user_id
            LEFT JOIN users u_d ON u_d.user_id=uc.nguoi_duyet_id
            WHERE uc.id=?', [$appId]);
        return self::mapApplication($row);
    }
}
