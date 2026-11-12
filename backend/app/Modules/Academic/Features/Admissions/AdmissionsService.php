<?php
namespace App\Modules\Academic\Features\Admissions;

use App\Shared\{Access, ApiException, Store};
use App\Modules\People\Features\Teachers\TeachersRepository;

final class AdmissionsService
{
    public static function requests(array $input): array
    {
        $actor = AdmissionsFilter::staff();
        $filters = AdmissionsValidator::listing($input);
        if ($filters['trangThai'] && !in_array($filters['trangThai'], ['moi', 'dang_tim', 'da_de_xuat', 'da_chot', 'huy'], true)) \App\Shared\Input::fail('trangThai');
        return AdmissionsRepository::requests($filters, $actor);
    }

    public static function detail(int $id): array
    {
        $request = AdmissionsRepository::find($id, AdmissionsFilter::staff());
        return AdmissionsModel::map($request) + ['lichYeuCau'=>AdmissionsRepository::slots($id)];
    }

    public static function lookups(array $input): array
    {
        $actor = AdmissionsFilter::staff();
        AdmissionsValidator::fields($input, ['q']);
        $q = \App\Shared\Input::validate($input, ['q'=>['string', false]])['q'] ?? '';
        if (mb_strlen($q) > 150) \App\Shared\Input::fail('q');
        $campuses = Store::rows('SELECT id,ten_co_so AS ten FROM co_so WHERE is_active=1'.
            ($actor['role'] !== 'ADMIN' && $actor['coSoId'] ? ' AND id='.(int)$actor['coSoId'] : '').' ORDER BY ten_co_so');
        $studentScope = $actor['role'] !== 'ADMIN' && $actor['coSoId'] ? AdmissionsFilter::studentScope('hv.id', (int)$actor['coSoId']) : '';
        return ['subjects'=>Store::rows('SELECT id,ten FROM mon_hoc WHERE is_active=1 ORDER BY ten'),
            'grades'=>Store::rows('SELECT id,ten FROM cap_lop ORDER BY id'),
            'regions'=>Store::rows('SELECT id,ten FROM khu_vuc WHERE is_active=1 ORDER BY ten'), 'campuses'=>$campuses,
            'students'=>Store::rows('SELECT hv.id,hv.ma_hv,hv.ho_ten,hv.cap_lop_id,hv.khu_vuc_id,
                ph.hoten AS ten_phu_huynh,ph.sdt AS sdt_phu_huynh,ph.email AS email_phu_huynh
                FROM hoc_vien hv JOIN users ph ON ph.user_id=hv.phu_huynh_user_id
                WHERE hv.deleted_at IS NULL AND ph.is_deleted=0'.$studentScope.' AND (hv.ho_ten LIKE ? OR hv.ma_hv LIKE ?)
                ORDER BY hv.ho_ten LIMIT 100', ['%'.$q.'%', '%'.$q.'%'])];
    }

    public static function save(array $input, ?int $id = null): array
    {
        $actor = AdmissionsFilter::staff($id === null ? 'add' : 'edit');
        $data = AdmissionsValidator::request($input);
        return Store::transaction(function () use ($id, $data, $actor) {
            $before = $id === null ? null : AdmissionsRepository::find($id, $actor, true);
            if ($before && (in_array($before['trang_thai'], ['da_chot', 'huy'], true)
                || Store::one('SELECT id FROM de_xuat_nhan_su WHERE yeu_cau_id=? LIMIT 1', [$id]))) {
                throw new ApiException(409, 'Yêu cầu đã gửi đề xuất hoặc kết thúc, hãy giữ thông tin đã gửi cho hai bên.');
            }
            AdmissionsRepository::validateRelations($data, $actor);
            $employee = AdmissionsFilter::employee($actor);
            $requestId = AdmissionsRepository::saveRequest($data, $employee, (int)$actor['userId'], $id);
            $after = AdmissionsRepository::find($requestId, $actor);
            Store::recordAudit((int)$actor['userId'], $id === null ? 'them' : 'sua', 'yeu_cau_hoc', $requestId,
                $before, $after + ['lichYeuCau'=>$data['lichYeuCau']]);
            return AdmissionsModel::map($after) + ['lichYeuCau'=>AdmissionsRepository::slots($requestId)];
        });
    }

    public static function receive(int $id, array $input): array
    {
        $actor = AdmissionsFilter::staff('edit');
        AdmissionsValidator::fields($input, []);
        return Store::transaction(function () use ($id, $actor) {
            $before = AdmissionsRepository::find($id, $actor, true);
            if (in_array($before['trang_thai'], ['huy', 'da_chot'], true)) throw new ApiException(409, 'Yêu cầu đã kết thúc.');
            $employee = AdmissionsFilter::employee($actor);
            Store::execute("UPDATE yeu_cau_hoc SET nhan_vien_id=COALESCE(nhan_vien_id,?),
                trang_thai=IF(trang_thai='moi','dang_tim',trang_thai) WHERE id=?", [$employee, $id]);
            $after = AdmissionsRepository::find($id, $actor);
            if ($before !== $after) Store::recordAudit((int)$actor['userId'], 'sua', 'yeu_cau_hoc', $id, $before, $after);
            return AdmissionsModel::map($after) + ['lichYeuCau'=>AdmissionsRepository::slots($id)];
        });
    }

    private static function matching(array $request): array
    {
        $filters = ['monHocId'=>$request['mon_hoc_id'], 'capLopId'=>$request['cap_lop_id']];
        if ($request['khu_vuc_id']) $filters['khuVucId'] = $request['khu_vuc_id'];
        $slots = AdmissionsRepository::slots((int)$request['id']);
        $people = [];
        foreach (TeachersRepository::match($filters) as $profile) {
            $candidate = AdmissionsRepository::candidate($request, $slots, $profile);
            if ($candidate) $people[$candidate['id']] = $candidate;
        }
        return array_values($people);
    }

    public static function candidates(int $id): array
    {
        $request = AdmissionsRepository::find($id, AdmissionsFilter::staff());
        if (in_array($request['trang_thai'], ['huy', 'da_chot'], true)) throw new ApiException(409, 'Yêu cầu đã kết thúc.');
        return self::matching($request);
    }

    private static function parentId(array $request): ?int
    {
        if ($request['phu_huynh_user_id']) return (int)$request['phu_huynh_user_id'];
        $parent = Store::one("SELECT a.user_id FROM account a JOIN role_group g ON g.group_id=a.group_id
            JOIN users u ON u.user_id=a.user_id WHERE a.user_id=? AND a.is_deleted=0 AND u.is_deleted=0 AND g.group_name='PhuHuynh' AND g.is_deleted=0",
            [$request['nguoi_gui_id']]);
        return $parent ? (int)$parent['user_id'] : null;
    }

    public static function propose(int $id, array $input): array
    {
        $actor = AdmissionsFilter::staff('add');
        $data = AdmissionsValidator::proposal($input);
        return Store::transaction(function () use ($id, $actor, $data) {
            $request = AdmissionsRepository::find($id, $actor, true);
            if (in_array($request['trang_thai'], ['huy', 'da_chot'], true)) throw new ApiException(409, 'Yêu cầu đã kết thúc.');
            $parentId = self::parentId($request);
            if ($parentId === null) throw new ApiException(409, 'Liên kết hồ sơ học viên và phụ huynh trước khi gửi đề xuất để phụ huynh có thể phản hồi.');
            if (Store::one('SELECT id FROM de_xuat_nhan_su WHERE yeu_cau_id=? AND nhan_su_id=?', [$id, $data['nhanSuId']])) {
                throw new ApiException(409, 'Người dạy này đã được đề xuất cho yêu cầu.');
            }
            $profile = Store::one('SELECT id FROM nhan_su_chuyen_mon WHERE id=? FOR UPDATE', [$data['nhanSuId']]);
            if (!$profile || !in_array($data['nhanSuId'], array_column(self::matching($request), 'id'), true)) {
                throw new ApiException(409, 'Người dạy không còn khớp chuyên môn, khu vực, lịch rảnh, lịch dạy hoặc ngân sách. Hãy lọc lại.');
            }
            $employee = AdmissionsFilter::employee($actor);
            $proposalId = Store::insert('de_xuat_nhan_su', ['yeu_cau_id'=>$id, 'nhan_su_id'=>$data['nhanSuId'],
                'nhan_vien_id'=>$employee, 'hoc_phi_de_xuat'=>$data['hocPhiDeXuat'], 'cach_thu_phi_de_xuat'=>'theo_khoa', 'ghi_chu'=>$data['ghiChu']]);
            Store::execute("UPDATE yeu_cau_hoc SET trang_thai='da_de_xuat',nhan_vien_id=COALESCE(nhan_vien_id,?) WHERE id=?", [$employee, $id]);
            $proposal = Store::one('SELECT * FROM de_xuat_nhan_su WHERE id=?', [$proposalId]);
            $teacher = Store::one('SELECT user_id FROM nhan_su_chuyen_mon WHERE id=?', [$data['nhanSuId']]);
            foreach (array_unique([$parentId, (int)$teacher['user_id']]) as $recipient) {
                AdmissionsRepository::notify($recipient, $proposalId, 'Đề xuất người dạy '.$request['ma_yc'], 'Vui lòng xem và phản hồi đề xuất DX-'.$proposalId.'.', 'created');
            }
            Store::recordAudit((int)$actor['userId'], 'them', 'de_xuat_nhan_su', $proposalId, null, $proposal);
            return AdmissionsModel::map($proposal);
        });
    }

    public static function proposals(array $input): array
    {
        $actor = Access::user();
        $actor = Access::staff($actor) ? AdmissionsFilter::staff() : AdmissionsFilter::participant();
        $filters = AdmissionsValidator::listing($input);
        if ($filters['trangThai'] && $filters['trangThai'] !== 'san_sang') \App\Shared\Input::fail('trangThai');
        return AdmissionsRepository::proposals($filters, $actor);
    }

    public static function respond(int $id, array $input): array
    {
        $actor = AdmissionsFilter::participant();
        $data = AdmissionsValidator::response($input);
        return Store::transaction(function () use ($id, $actor, $data) {
            $identity = Store::one('SELECT yeu_cau_id FROM de_xuat_nhan_su WHERE id=?', [$id]);
            if (!$identity) throw new ApiException(404, 'Không tìm thấy đề xuất.');
            // Every response locks its request first, so two acceptances cannot select different winners.
            $request = Store::one('SELECT y.*,hv.phu_huynh_user_id FROM yeu_cau_hoc y
                LEFT JOIN hoc_vien hv ON hv.id=y.hoc_vien_id AND hv.deleted_at IS NULL WHERE y.id=? FOR UPDATE', [$identity['yeu_cau_id']]);
            $proposal = Store::one('SELECT dx.*,ns.user_id AS nguoi_day_user_id FROM de_xuat_nhan_su dx
                JOIN nhan_su_chuyen_mon ns ON ns.id=dx.nhan_su_id WHERE dx.id=? FOR UPDATE', [$id]);
            $parent = $actor['role'] === 'PHU_HUYNH';
            $owner = $parent ? self::parentId($request) : (int)$proposal['nguoi_day_user_id'];
            if ($owner !== (int)$actor['userId']) throw new ApiException(404, 'Không tìm thấy đề xuất.');
            $column = $parent ? 'tt_phu_huynh' : 'tt_nhan_su';
            $reason = $parent ? 'ly_do_tu_choi_phu_huynh' : 'ly_do_tu_choi_nhan_su';
            if ($proposal[$column] !== 'cho') {
                if ($proposal[$column] === $data['trangThai'] && $proposal[$reason] === $data['lyDo']) {
                    unset($proposal['nguoi_day_user_id']);
                    return AdmissionsModel::map($proposal);
                }
                throw new ApiException(409, 'Phản hồi đã ghi nhận, hãy liên hệ giáo vụ nếu cần thay đổi.');
            }
            if (in_array($request['trang_thai'], ['huy', 'da_chot'], true)) throw new ApiException(409, 'Yêu cầu đã kết thúc hoặc đã chốt đề xuất khác.');
            $timestamp = $parent ? 'phu_huynh_phan_hoi_luc' : 'nhan_su_phan_hoi_luc';
            Store::execute("UPDATE de_xuat_nhan_su SET $column=?,$reason=?,$timestamp=NOW()".
                ($parent ? ',phu_huynh_phan_hoi_boi='.(int)$actor['userId'] : '').' WHERE id=?', [$data['trangThai'], $data['lyDo'], $id]);
            $after = Store::one('SELECT * FROM de_xuat_nhan_su WHERE id=?', [$id]);
            $ready = $after['tt_phu_huynh'] === 'dong_y' && $after['tt_nhan_su'] === 'dong_y';
            if ($ready) Store::execute("UPDATE yeu_cau_hoc SET trang_thai='da_chot' WHERE id=?", [$request['id']]);
            Store::recordAudit((int)$actor['userId'], 'sua', 'de_xuat_nhan_su', $id, $proposal, $after);
            $recipients = Store::rows('SELECT user_id FROM nhan_vien WHERE id=?', [$proposal['nhan_vien_id']]);
            if ($ready) $recipients = array_merge($recipients, Store::rows("SELECT a.user_id FROM account a JOIN role_group g ON g.group_id=a.group_id
                WHERE g.group_name='Admin' AND a.status='active' AND a.is_deleted=0 AND g.is_deleted=0"));
            foreach (array_unique(array_column($recipients, 'user_id')) as $recipient) {
                AdmissionsRepository::notify((int)$recipient, $id, $ready ? 'Đề xuất sẵn sàng bàn giao Admin' : 'Đề xuất có phản hồi mới',
                    'DX-'.$id.' · '.$request['ma_yc'].' · '.($parent ? 'Phụ huynh' : 'Người dạy').' '.($data['trangThai'] === 'dong_y' ? 'đồng ý' : 'từ chối'),
                    $parent ? 'parent-response' : 'teacher-response');
            }
            return AdmissionsModel::map($after);
        });
    }

    public static function registrations(array $input): array
    {
        $actor = AdmissionsFilter::staff();
        $filters = AdmissionsValidator::listing($input);
        if ($filters['trangThai'] && !in_array($filters['trangThai'], ['cho_xep_lop', 'dang_hoc', 'hoan_thanh', 'thoi_hoc'], true)) \App\Shared\Input::fail('trangThai');
        return AdmissionsRepository::registrations($filters, $actor);
    }
}
