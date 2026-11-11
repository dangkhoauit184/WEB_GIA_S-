<?php
namespace App\Modules\Academic\Features\Admissions;

use App\Shared\{ApiException, Store};
use App\Modules\People\Features\Teachers\TeachersRepository;

final class AdmissionsRepository
{
    public const REQUEST_FROM = ' FROM yeu_cau_hoc y
        LEFT JOIN hoc_vien hv ON hv.id=y.hoc_vien_id AND hv.deleted_at IS NULL
        LEFT JOIN users ph ON ph.user_id=hv.phu_huynh_user_id AND ph.is_deleted=0
        JOIN mon_hoc m ON m.id=y.mon_hoc_id JOIN cap_lop cl ON cl.id=y.cap_lop_id
        LEFT JOIN khu_vuc kv ON kv.id=y.khu_vuc_id
        LEFT JOIN nhan_vien nv ON nv.id=y.nhan_vien_id LEFT JOIN users op ON op.user_id=nv.user_id';
    private const REQUEST_SELECT = 'SELECT y.*,hv.ho_ten AS ten_hoc_vien,hv.ma_hv,
        hv.phu_huynh_user_id,ph.hoten AS ten_phu_huynh,ph.sdt AS sdt_phu_huynh,
        m.ten AS ten_mon_hoc,cl.ten AS ten_cap_lop,kv.ten AS ten_khu_vuc,op.hoten AS ten_nhan_vien';
    private const REQUEST_FIELDS = ['hocVienId'=>'hoc_vien_id', 'hoTenLienHe'=>'ho_ten_lien_he',
        'sdtLienHe'=>'sdt_lien_he', 'emailLienHe'=>'email_lien_he', 'monHocId'=>'mon_hoc_id', 'capLopId'=>'cap_lop_id',
        'loaiLopMongMuon'=>'loai_lop_mong_muon', 'coSoMongMuonId'=>'co_so_mong_muon_id', 'hinhThuc'=>'hinh_thuc',
        'khuVucId'=>'khu_vuc_id', 'diaChiHoc'=>'dia_chi_hoc', 'nganSachMin'=>'ngan_sach_min',
        'nganSachMax'=>'ngan_sach_max', 'donViNganSach'=>'don_vi_ngan_sach', 'mucTieu'=>'muc_tieu',
        'ghiChu'=>'ghi_chu', 'nguon'=>'nguon'];

    private static function page(string $select, string $from, string $where, array $params, array $filters, string $order = 'y.id DESC'): array
    {
        $total = (int)Store::one('SELECT COUNT(*) AS total'.$from.$where, $params)['total'];
        $limit = $filters['perPage'];
        $offset = ($filters['page'] - 1) * $limit;
        $rows = Store::rows($select.$from.$where." ORDER BY $order LIMIT $limit OFFSET $offset", $params);
        return ['items'=>array_map([AdmissionsModel::class, 'map'], $rows), 'total'=>$total,
            'page'=>$filters['page'], 'perPage'=>$limit];
    }

    public static function requests(array $filters, array $actor): array
    {
        [$scope, $params] = AdmissionsFilter::scope($actor);
        $where = ' WHERE 1=1'.$scope;
        if ($filters['trangThai']) { $where .= ' AND y.trang_thai=?'; $params[] = $filters['trangThai']; }
        if ($filters['q']) {
            $where .= ' AND (y.ma_yc LIKE ? OR hv.ho_ten LIKE ? OR ph.hoten LIKE ? OR y.ho_ten_lien_he LIKE ? OR COALESCE(y.sdt_lien_he,ph.sdt) LIKE ?)';
            array_push($params, ...array_fill(0, 5, '%'.$filters['q'].'%'));
        }
        $page = self::page(self::REQUEST_SELECT, self::REQUEST_FROM, $where, $params, $filters);
        foreach ($page['items'] as &$row) $row['lichYeuCau'] = self::slots((int)$row['id']);
        return $page;
    }

    public static function find(int $id, array $actor, bool $lock = false): array
    {
        [$scope, $params] = AdmissionsFilter::scope($actor);
        return Store::one(self::REQUEST_SELECT.self::REQUEST_FROM.' WHERE y.id=?'.$scope.($lock ? ' FOR UPDATE' : ''), [$id, ...$params])
            ?? throw new ApiException(404, 'Không tìm thấy yêu cầu học.', 'REQUEST_NOT_FOUND');
    }

    public static function slots(int $id): array
    {
        return array_map([AdmissionsModel::class, 'map'], Store::rows(
            'SELECT thu,gio_bat_dau,gio_ket_thuc FROM yeu_cau_lich WHERE yeu_cau_id=? ORDER BY thu,gio_bat_dau', [$id]));
    }

    public static function saveRequest(array $data, int $employee, int $sender, ?int $id = null): int
    {
        if ($id === null) {
            $values = ['ma_yc'=>'YC-'.strtoupper(bin2hex(random_bytes(8))), 'nhan_vien_id'=>$employee, 'nguoi_gui_id'=>$sender];
            foreach (self::REQUEST_FIELDS as $key=>$column) $values[$column] = $data[$key];
            $id = Store::insert('yeu_cau_hoc', $values);
        } else {
            Store::update('yeu_cau_hoc', 'id', $id, $data, self::REQUEST_FIELDS);
            Store::execute('DELETE FROM yeu_cau_lich WHERE yeu_cau_id=?', [$id]);
        }
        foreach ($data['lichYeuCau'] as $slot) Store::insert('yeu_cau_lich', ['yeu_cau_id'=>$id, 'thu'=>$slot['thu'],
            'gio_bat_dau'=>$slot['gioBatDau'], 'gio_ket_thuc'=>$slot['gioKetThuc']]);
        return $id;
    }

    public static function validateRelations(array $data, array $actor): void
    {
        foreach (['monHocId'=>'mon_hoc', 'capLopId'=>'cap_lop', 'khuVucId'=>'khu_vuc', 'coSoMongMuonId'=>'co_so'] as $key=>$table) {
            if (isset($data[$key]) && !Store::one("SELECT id FROM $table WHERE id=?".($table === 'cap_lop' ? '' : ' AND is_active=1'), [$data[$key]])) {
                throw new ApiException(400, 'Môn, khối, khu vực hoặc cơ sở không còn hoạt động.');
            }
        }
        if ($actor['role'] !== 'ADMIN' && $actor['coSoId'] && $data['coSoMongMuonId'] && (int)$data['coSoMongMuonId'] !== (int)$actor['coSoId']) {
            throw new ApiException(403, 'Cơ sở nằm ngoài phạm vi phụ trách.');
        }
        $studentScope = $actor['role'] !== 'ADMIN' && $actor['coSoId'] ? AdmissionsFilter::studentScope('hv.id', (int)$actor['coSoId']) : '';
        if ($data['hocVienId'] && !Store::one('SELECT hv.id FROM hoc_vien hv JOIN users ph ON ph.user_id=hv.phu_huynh_user_id
            WHERE hv.id=? AND hv.deleted_at IS NULL AND ph.is_deleted=0'.$studentScope, [$data['hocVienId']])) throw new ApiException(400, 'Hồ sơ học viên không hợp lệ hoặc ngoài phạm vi cơ sở.');
    }

    public static function registrations(array $filters, array $actor): array
    {
        $from = ' FROM dang_ky_khoa_hoc y JOIN hoc_vien hv ON hv.id=y.hoc_vien_id
            JOIN users ph ON ph.user_id=hv.phu_huynh_user_id JOIN khoa_hoc k ON k.id=y.khoa_hoc_id';
        $where = ' WHERE hv.deleted_at IS NULL AND ph.is_deleted=0';
        $params = [];
        if ($actor['role'] !== 'ADMIN' && $actor['coSoId']) {
            $where .= " AND (NOT EXISTS (SELECT 1 FROM lop_hoc_vien x WHERE x.dang_ky_khoa_hoc_id=y.id AND x.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop'))
                OR EXISTS (SELECT 1 FROM lop_hoc_vien x JOIN lop_hoc l ON l.id=x.lop_id WHERE x.dang_ky_khoa_hoc_id=y.id AND l.co_so_id=? AND x.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop')))";
            $params[] = $actor['coSoId'];
        }
        if ($filters['trangThai']) { $where .= ' AND y.trang_thai=?'; $params[] = $filters['trangThai']; }
        if ($filters['q']) {
            $where .= ' AND (hv.ho_ten LIKE ? OR hv.ma_hv LIKE ? OR ph.hoten LIKE ? OR k.ten LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%'.$filters['q'].'%'));
        }
        return self::page("SELECT y.id,y.hoc_vien_id,y.khoa_hoc_id,y.trang_thai,y.hoc_phi_snapshot,y.ngay_dang_ky,
            hv.ho_ten AS ten_hoc_vien,hv.ma_hv,ph.hoten AS ten_phu_huynh,ph.sdt AS sdt_phu_huynh,k.ten AS ten_khoa,k.ma AS ma_khoa,
            (SELECT GROUP_CONCAT(l.ma_lop SEPARATOR ', ') FROM lop_hoc_vien x JOIN lop_hoc l ON l.id=x.lop_id
                WHERE x.dang_ky_khoa_hoc_id=y.id AND x.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop')) AS ma_lop", $from, $where, $params, $filters);
    }

    public static function proposals(array $filters, array $actor): array
    {
        [$scope, $params] = AdmissionsFilter::scope($actor);
        $from = ' FROM de_xuat_nhan_su dx JOIN yeu_cau_hoc y ON y.id=dx.yeu_cau_id
            JOIN nhan_su_chuyen_mon ns ON ns.id=dx.nhan_su_id JOIN users u ON u.user_id=ns.user_id
            LEFT JOIN hoc_vien hv ON hv.id=y.hoc_vien_id AND hv.deleted_at IS NULL';
        $where = ' WHERE 1=1';
        if (in_array($actor['role'], ['ADMIN', 'GIAO_VU'], true)) $where .= $scope;
        elseif ($actor['role'] === 'PHU_HUYNH') {
            $where .= ' AND (hv.phu_huynh_user_id=? OR (y.hoc_vien_id IS NULL AND y.nguoi_gui_id=?))';
            $params = [$actor['userId'], $actor['userId']];
        } else { $where .= ' AND ns.user_id=?'; $params = [$actor['userId']]; }
        if ($filters['trangThai'] === 'san_sang') $where .= " AND dx.tt_nhan_su='dong_y' AND dx.tt_phu_huynh='dong_y' AND y.trang_thai='da_chot'";
        if ($filters['q']) {
            $where .= ' AND (y.ma_yc LIKE ? OR u.hoten LIKE ? OR hv.ho_ten LIKE ? OR y.ho_ten_lien_he LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%'.$filters['q'].'%'));
        }
        return self::page('SELECT dx.*,y.ma_yc,y.trang_thai AS trang_thai_yeu_cau,y.hoc_vien_id,
            hv.ho_ten AS ten_hoc_vien,y.ho_ten_lien_he,u.hoten AS ten_nguoi_day,ns.ma_ns,
            (SELECT GROUP_CONCAT(l.ma_lop SEPARATOR \', \') FROM lop_hoc l WHERE l.de_xuat_id=dx.id AND l.deleted_at IS NULL) AS ma_lop',
            $from, $where, $params, $filters, 'dx.id DESC');
    }

    public static function candidate(array $request, array $slots, array $profile): ?array
    {
        if (!$slots || !array_intersect(explode(',', $profile['phan_loai']), ['gia_su', 'giao_vien'])) return null;
        $extras = TeachersRepository::extras((int)$profile['id']);
        foreach ($slots as $slot) {
            $covered = array_filter($extras['lichRanhs'], static fn($free)=>
                (int)$free['thu'] === (int)$slot['thu'] && $free['gioBatDau'] <= $slot['gioBatDau'] && $free['gioKetThuc'] >= $slot['gioKetThuc']);
            if (!$covered || self::conflicts($profile, $slot)) return null;
            if ($request['don_vi_ngan_sach'] === 'theo_buoi' && !self::withinBudget((float)$profile['hoc_phi_buoi_de_xuat'], $request)) return null;
            if ($request['hinh_thuc'] !== 'ca_hai' && !in_array($request['hinh_thuc'], explode(',', $profile['hinh_thuc_day']), true)) return null;
        }
        // Per-course/per-hour budgets cannot be equated with a per-session profile price.
        return ['id'=>(int)$profile['id'], 'userId'=>(int)$profile['user_id'], 'maNs'=>$profile['ma_ns'],
            'hoTen'=>$profile['hoten'], 'donViCongTac'=>$profile['don_vi_cong_tac'], 'kinhNghiemNam'=>(int)$profile['kinh_nghiem_nam'],
            'gioiThieu'=>$profile['gioi_thieu'], 'hocPhiBuoiDeXuat'=>(float)$profile['hoc_phi_buoi_de_xuat'],
            'monDays'=>$extras['monDays'], 'khuVucs'=>$extras['khuVucs'], 'lichRanhs'=>$extras['lichRanhs'],
            'nganSachCanThoaThuan'=>isset($request['ngan_sach_min']) || isset($request['ngan_sach_max'])
                ? $request['don_vi_ngan_sach'] !== 'theo_buoi' : false];
    }

    private static function withinBudget(float $fee, array $request): bool
    {
        return (!isset($request['ngan_sach_min']) || $fee >= (float)$request['ngan_sach_min'])
            && (!isset($request['ngan_sach_max']) || $fee <= (float)$request['ngan_sach_max']);
    }

    private static function conflicts(array $profile, array $slot): bool
    {
        if (Store::one("SELECT pc.id FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
            JOIN lop_hoc l ON l.id=b.lop_id WHERE pc.user_id=? AND pc.trang_thai<>'da_go' AND l.deleted_at IS NULL
            AND b.trang_thai<>'huy' AND TIMESTAMP(b.ngay,b.gio_ket_thuc)>NOW() AND WEEKDAY(b.ngay)+2=?
            AND b.gio_bat_dau<? AND b.gio_ket_thuc>? LIMIT 1", [$profile['user_id'], $slot['thu'], $slot['gioKetThuc'], $slot['gioBatDau']])) return true;
        $weekly = Store::rows("SELECT GREATEST(CURRENT_DATE(),l.ngay_bat_dau,lh.hieu_luc_tu) AS tu_ngay,
            LEAST(COALESCE(l.ngay_ket_thuc,'9999-12-31'),COALESCE(lh.hieu_luc_den,'9999-12-31')) AS den_ngay
            FROM lich_hoc lh JOIN lop_hoc l ON l.id=lh.lop_id WHERE l.nguoi_day_chinh_id=? AND lh.is_active=1
            AND l.deleted_at IS NULL AND l.trang_thai IN ('sap_bat_dau','dang_hoc') AND lh.thu=?
            AND lh.gio_bat_dau<? AND lh.gio_ket_thuc>?", [$profile['id'], $slot['thu'], $slot['gioKetThuc'], $slot['gioBatDau']]);
        foreach ($weekly as $busy) {
            $start = new \DateTimeImmutable($busy['tu_ngay']);
            $offset = ((int)$slot['thu'] - 2 - (int)$start->format('N') + 1 + 7) % 7;
            if ($start->modify("+$offset days")->format('Y-m-d') <= $busy['den_ngay']) return true;
        }
        return false;
    }

    public static function notify(int $userId, int $proposalId, string $title, string $message, string $event): void
    {
        Store::insert('thong_bao', ['user_id'=>$userId, 'loai'=>'de_xuat', 'tieu_de'=>$title, 'noi_dung'=>$message,
            'doi_tuong_loai'=>'de_xuat_nhan_su', 'doi_tuong_id'=>$proposalId, 'khoa_chong_trung'=>"admissions:$proposalId:$event"]);
    }
}
