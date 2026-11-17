<?php
namespace App\Modules\People\Features\Teachers;

use App\Shared\ApiException;
use App\Shared\Store;
use App\Modules\Academic\Features\Attendance\{AttendanceFilter, AttendanceValidator};
use App\Modules\Academic\Features\Admissions\{AdmissionsRepository, AdmissionsModel, AdmissionsService};

final class TeachersService
{
    public static function saveAvailability(array $body, ?int $id = null): array
    {
        $actor = AttendanceFilter::teacher();
        AttendanceValidator::fields($body, ['thu','gioBatDau','gioKetThuc']);
        $slot = TeachersValidator::validate(['lichRanhs'=>[$body]], false)['lichRanhs'][0];
        return TeachersRepository::availability($actor, $id, $slot);
    }

    public static function deleteAvailability(int $id): array
    {
        return TeachersRepository::availability(AttendanceFilter::teacher(), $id, null);
    }

    public static function updateProfile(array $body): array
    {
        $actor = AttendanceFilter::teacher();
        AttendanceValidator::fields($body, ['hoTen','email','ngaySinh','gioiTinh','sdt','diaChi','donViCongTac','gioiThieu','lichRanhs']);
        foreach (['hoTen'=>150,'email'=>150,'sdt'=>20,'diaChi'=>255,'donViCongTac'=>255,'gioiThieu'=>10000] as $field=>$limit) {
            if (array_key_exists($field, $body)) $body[$field] = AttendanceValidator::text($body[$field], $limit);
        }
        if (isset($body['lichRanhs']) && (!is_array($body['lichRanhs']) || count($body['lichRanhs']) > 100)) throw new ApiException(400, 'Lịch rảnh không hợp lệ.');
        $data = TeachersValidator::validate($body, false);
        $slots = $data['lichRanhs'] ?? [];
        foreach ($slots as $index=>$slot) {
            foreach (array_slice($slots, $index + 1) as $other) {
                if ($slot['thu'] === $other['thu'] && $slot['gioBatDau'] < $other['gioKetThuc'] && $slot['gioKetThuc'] > $other['gioBatDau']) {
                    throw new ApiException(400, 'Các khung giờ rảnh đang trùng nhau.');
                }
            }
        }
        TeachersRepository::update($actor['teacherId'], $actor['userId'], $data, $actor['userId']);
        return self::detail($actor['teacherId']);
    }

    private static function candidate(array $request, array $actor): ?array
    {
        $profiles = TeachersRepository::match(['monHocId'=>$request['mon_hoc_id'], 'capLopId'=>$request['cap_lop_id'],
            'khuVucId'=>$request['hinh_thuc'] === 'online' ? null : $request['khu_vuc_id']], 'giao_vien');
        foreach ($profiles as $profile) {
            if ((int)$profile['id'] === $actor['teacherId']) return AdmissionsRepository::candidate($request, AdmissionsRepository::slots((int)$request['id']), $profile);
        }
        return null;
    }

    public static function opportunities(): array
    {
        $actor = AttendanceFilter::teacher();
        $requests = Store::rows("SELECT y.*,m.ten AS ten_mon,cl.ten AS ten_cap_lop,kv.ten AS ten_khu_vuc
            FROM yeu_cau_hoc y JOIN mon_hoc m ON m.id=y.mon_hoc_id JOIN cap_lop cl ON cl.id=y.cap_lop_id
            LEFT JOIN khu_vuc kv ON kv.id=y.khu_vuc_id WHERE y.trang_thai IN ('moi','dang_tim','da_de_xuat')
            AND EXISTS (SELECT 1 FROM nhan_vien nv WHERE nv.id=y.nhan_vien_id AND nv.trang_thai='dang_lam')
            AND (y.nguoi_gui_id IS NOT NULL OR EXISTS (SELECT 1 FROM hoc_vien hv WHERE hv.id=y.hoc_vien_id AND hv.deleted_at IS NULL))
            AND NOT EXISTS (SELECT 1 FROM de_xuat_nhan_su dx WHERE dx.yeu_cau_id=y.id AND dx.nhan_su_id=?) ORDER BY y.id DESC", [$actor['teacherId']]);
        $result = [];
        foreach ($requests as $request) {
            $parent = Store::one('SELECT phu_huynh_user_id FROM hoc_vien WHERE id=? AND deleted_at IS NULL', [$request['hoc_vien_id']]);
            if (!AdmissionsService::parentId($request + ['phu_huynh_user_id'=>$parent['phu_huynh_user_id'] ?? null]) || !self::candidate($request, $actor)) continue;
            // A public opportunity contains requirements, never parent contact details or fees.
            $result[] = AdmissionsModel::map(['id'=>$request['id'], 'ma_yc'=>$request['ma_yc'], 'ten_mon'=>$request['ten_mon'],
                'ten_cap_lop'=>$request['ten_cap_lop'], 'hinh_thuc'=>$request['hinh_thuc'], 'ten_khu_vuc'=>$request['ten_khu_vuc'],
                'muc_tieu'=>$request['muc_tieu'], 'lichYeuCau'=>AdmissionsRepository::slots((int)$request['id'])]);
        }
        return $result;
    }

    public static function apply(int $id): array
    {
        $actor = AttendanceFilter::teacher();
        return Store::transaction(function () use ($id, $actor) {
            $request = Store::one('SELECT * FROM yeu_cau_hoc WHERE id=? FOR UPDATE', [$id]);
            if (!$request || !in_array($request['trang_thai'], ['moi','dang_tim','da_de_xuat'], true)) throw new ApiException(409, 'Yêu cầu học đã đóng.');
            $existing = Store::one('SELECT id,tt_nhan_su FROM de_xuat_nhan_su WHERE yeu_cau_id=? AND nhan_su_id=?', [$id, $actor['teacherId']]);
            if ($existing) return ['id'=>$existing['id'], 'ttNhanSu'=>$existing['tt_nhan_su']];
            Store::one('SELECT id FROM nhan_su_chuyen_mon WHERE id=? FOR UPDATE', [$actor['teacherId']]);
            if (!self::candidate($request, $actor)) throw new ApiException(409, 'Yêu cầu chưa khớp chuyên môn hoặc lịch rảnh hiện tại.');
            $employee = Store::one("SELECT id FROM nhan_vien WHERE id=? AND trang_thai='dang_lam'", [$request['nhan_vien_id']]);
            if (!$employee) throw new ApiException(409, 'Yêu cầu chưa có giáo vụ tiếp nhận.');
            $parent = Store::one('SELECT phu_huynh_user_id FROM hoc_vien WHERE id=? AND deleted_at IS NULL', [$request['hoc_vien_id']]);
            $parentId = AdmissionsService::parentId($request + ['phu_huynh_user_id'=>$parent['phu_huynh_user_id'] ?? null]);
            if (!$parentId) throw new ApiException(409, 'Yêu cầu chưa liên kết người gửi để nhận phản hồi.');
            $proposal = Store::insert('de_xuat_nhan_su', ['yeu_cau_id'=>$id, 'nhan_su_id'=>$actor['teacherId'],
                'nhan_vien_id'=>$employee['id'], 'tt_nhan_su'=>'dong_y', 'nhan_su_phan_hoi_luc'=>Store::one('SELECT NOW() AS now')['now'],
                'ghi_chu'=>'Giáo viên ứng tuyển nhận lớp']);
            Store::execute("UPDATE yeu_cau_hoc SET trang_thai='da_de_xuat' WHERE id=?", [$id]);
            Store::recordAudit($actor['userId'], 'them', 'de_xuat_nhan_su', $proposal, null, ['thao_tac'=>'ung_tuyen', 'yeu_cau_id'=>$id]);
            $recipient = Store::one('SELECT user_id FROM nhan_vien WHERE id=?', [$employee['id']]);
            Store::insert('thong_bao', ['user_id'=>$recipient['user_id'], 'loai'=>'de_xuat', 'tieu_de'=>'Giáo viên ứng tuyển · '.$request['ma_yc'],
                'noi_dung'=>'Giáo viên đã xác nhận nhu cầu nhận lớp. Vui lòng theo dõi phản hồi phụ huynh.',
                'doi_tuong_loai'=>'de_xuat_nhan_su', 'doi_tuong_id'=>$proposal, 'khoa_chong_trung'=>'teacher-apply:'.$proposal]);
            AdmissionsRepository::notify((int)$parentId, $proposal, 'Có giáo viên nhận lớp '.$request['ma_yc'],
                'Vui lòng xem và phản hồi đề xuất DX-'.$proposal.'.', 'created');
            return ['id'=>$proposal, 'ttNhanSu'=>'dong_y'];
        });
    }
    public static function all(array $filters,?string $type=null): array
    {
        if($type) $filters['phanLoai']=$type;
        return array_map(fn($row)=>TeachersModel::map($row),TeachersRepository::all($filters,TeachersFilter::check()));
    }
    public static function detail(int $id,?string $type=null): array
    {
        $row=TeachersRepository::all($type?['phanLoai'=>$type]:[],TeachersFilter::check(),$id)[0]??null;
        if (!$row) throw new ApiException(404,'Không tìm thấy người dạy.','TEACHER_NOT_FOUND');
        return TeachersModel::map($row,TeachersRepository::extras($id));
    }
    public static function match(array $filters,?string $type=null): array
    {
        TeachersFilter::check('view',true);
        return array_map(fn($row)=>TeachersModel::map($row),TeachersRepository::match(TeachersValidator::match($filters),$type));
    }
    public static function create(array $input,string $type='giao_vien'): array
    {
        $actor=TeachersFilter::check('add',true);
        if(array_key_exists('donGiaGioChuan',$input) && $actor['role']!=='ADMIN') \App\Shared\Access::forbidden();
        return self::detail(TeachersRepository::create(TeachersValidator::validate($input,true,$type)));
    }
    public static function update(int $id,array $input,?string $type=null): array
    {
        TeachersFilter::check('edit',true); $row=self::detail($id,$type);
        if(array_key_exists('donGiaGioChuan',$input)) throw new ApiException(400,'Đổi đơn giá qua API quản trị đơn giá giờ để giữ snapshot ca đã dạy.');
        TeachersRepository::update($id,(int)$row['userId'],TeachersValidator::validate($input,false));
        return self::detail($id);
    }
}
