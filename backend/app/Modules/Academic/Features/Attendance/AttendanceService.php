<?php
namespace App\Modules\Academic\Features\Attendance;

use App\Shared\{Access, ApiException, Input, Store};
use App\Modules\People\Features\Teachers\TeachersService;
use CodeIgniter\HTTP\Files\UploadedFile;

final class AttendanceService
{
    public static function workspace(): array
    {
        $actor = AttendanceFilter::teacher();
        $now = Store::one('SELECT NOW() AS now')['now'];
        return AttendanceModel::map(['server_now'=>$now,
            'profile'=>TeachersService::detail($actor['teacherId']),
            'sessions'=>AttendanceRepository::sessions($actor['userId'], $now),
            'classes'=>AttendanceRepository::classes($actor), 'requests'=>AttendanceRepository::requests($actor['userId'])]);
    }

    public static function detail(int $id): array
    {
        $actor = AttendanceFilter::teacher();
        return AttendanceModel::map(AttendanceRepository::detail($id, $actor['userId']));
    }

    public static function uploadMaterial(int $id, ?UploadedFile $file, array $meta): array
    {
        $actor = AttendanceFilter::teacher();
        SessionMaterialService::saveMaterial(
            $id,
            $actor,
            static fn(int $sid, int $uid): array => AttendanceRepository::lock($sid, $uid),
            $file,
            $meta
        );
        return self::detail($id);
    }

    public static function removeMaterial(int $id): array
    {
        $actor = AttendanceFilter::teacher();
        SessionMaterialService::removeMaterial(
            $id,
            $actor,
            static fn(int $sid, int $uid): array => AttendanceRepository::lock($sid, $uid)
        );
        return self::detail($id);
    }

    public static function downloadMaterial(int $id): array
    {
        $user = Access::user();
        return SessionMaterialService::authorizeDownload($id, $user);
    }

    private static function writable(array $session): void
    {
        if (in_array($session['trang_thai'], ['huy', 'doi'], true)) throw new ApiException(409, 'Ca học đã hủy hoặc đổi lịch.');
        if ($session['ky_cong'] !== 'mo') throw new ApiException(409, 'Kỳ công đã khóa. Vui lòng gửi yêu cầu để Admin xem xét.');
        if ($session['vai_tro'] !== 'day_chinh') throw new ApiException(403, 'Ca này chưa phân công bạn làm giáo viên chính.');
    }

    public static function clock(int $id, string $direction, array $body): array
    {
        AttendanceValidator::fields($body, []);
        $actor = AttendanceFilter::teacher();
        Store::transaction(function () use ($id, $direction, $actor) {
            $session = AttendanceRepository::lock($id, $actor['userId']);
            // A replay returns the recorded server timestamp, including after a period closes.
            if ($direction === 'in' && $session['checkin_luc'] !== null) return;
            if ($direction === 'out' && $session['checkout_luc'] !== null) return;
            self::writable($session);
            if ($direction === 'in') {
                if ($session['trang_thai'] === 'da_day') throw new ApiException(409, 'Ca học đã kết thúc. Gửi yêu cầu bổ sung công.');
                Store::execute("UPDATE phan_cong_buoi_hoc SET checkin_luc=NOW(),trang_thai_cong='cho_duyet' WHERE id=?", [$session['phan_cong_id']]);
                Store::execute("UPDATE buoi_hoc SET trang_thai='dang_day' WHERE id=?", [$id]);
            } else {
                if ($session['checkin_luc'] === null) throw new ApiException(409, 'Bạn cần check-in trước.');
                $now = Store::one('SELECT NOW() AS now')['now'];
                if ($now <= $session['checkin_luc']) throw new ApiException(409, 'Giờ check-out phải sau check-in. Vui lòng thử lại.');
                Store::execute("UPDATE phan_cong_buoi_hoc SET checkout_luc=?,trang_thai='da_day',trang_thai_cong='hop_le' WHERE id=?", [$now, $session['phan_cong_id']]);
                Store::execute("UPDATE buoi_hoc SET trang_thai='da_day' WHERE id=?", [$id]);
            }
            Store::recordAudit($actor['userId'], 'sua', 'phan_cong_buoi_hoc', (int)$session['phan_cong_id'],
                ['checkin_luc'=>$session['checkin_luc'], 'checkout_luc'=>$session['checkout_luc']],
                ['thao_tac'=>$direction === 'in' ? 'checkin' : 'checkout']);
        });
        return self::detail($id);
    }

    public static function save(int $id, array $body): array
    {
        $actor = AttendanceFilter::teacher();
        $data = AttendanceValidator::log($body);
        Store::transaction(function () use ($id, $actor, $data) {
            $session = AttendanceRepository::lock($id, $actor['userId']);
            self::writable($session);
            if (!$session['checkin_luc']) throw new ApiException(409, 'Bạn cần check-in trước khi ghi sổ.');
            $before = AttendanceRepository::detail($id, $actor['userId']);
            if (!hash_equals($before['revision'], $data['revision'])) throw new ApiException(409, 'Sổ buổi học vừa thay đổi. Tải lại trước khi lưu.');
            $expected = array_map('intval', array_column($before['hocViens'], 'hoc_vien_id'));
            $received = array_column($data['hocViens'], 'hocVienId');
            sort($expected); sort($received);
            if ($expected !== $received) throw new ApiException(409, 'Danh sách học viên đã thay đổi hoặc không thuộc ca học.');
            Store::execute('UPDATE buoi_hoc SET chu_de_bai_hoc=?,noi_dung_bai_hoc=? WHERE id=?',
                [$data['chuDe'], $data['noiDung'], $id]);
            $requestedHomeworkId = isset($data['baiTapChamId']) && $data['baiTapChamId'] !== null
                ? (int)$data['baiTapChamId']
                : null;
            foreach ($data['hocViens'] as $student) {
                $existing = Store::one('SELECT id,bai_tap_id FROM so_theo_doi_buoi_hoc WHERE buoi_hoc_id=? AND hoc_vien_id=?', [$id, $student['hocVienId']]);
                $assignment = SessionMaterialService::resolveGradingHomeworkId(
                    $session,
                    $requestedHomeworkId,
                    $existing,
                    (int)$student['hocVienId'],
                    $student['diemBaiTapGiay'] !== null ? (float)$student['diemBaiTapGiay'] : null,
                    $student['nhanXetBaiTap']
                );
                $values = ['trang_thai_diem_danh'=>$student['trangThaiDiemDanh'], 'bai_tap_id'=>$assignment,
                    'diem_bai_tap_giay'=>$student['diemBaiTapGiay'], 'nhan_xet_bai_tap'=>$student['nhanXetBaiTap'],
                    'muc_tiep_thu'=>$student['mucTiepThu'], 'thai_do'=>$student['thaiDo'],
                    'nhan_xet_buoi_hoc'=>$student['nhanXetBuoiHoc'], 'huong_cai_thien'=>$student['huongCaiThien'], 'nguoi_nhap_id'=>$actor['userId']];
                if ($existing) Store::update('so_theo_doi_buoi_hoc', 'id', (int)$existing['id'], $values, array_combine(array_keys($values), array_keys($values)));
                else Store::insert('so_theo_doi_buoi_hoc', ['buoi_hoc_id'=>$id, 'hoc_vien_id'=>$student['hocVienId']] + $values);
                $parent = Store::one('SELECT phu_huynh_user_id FROM hoc_vien WHERE id=?', [$student['hocVienId']]);
                Store::execute("INSERT INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
                    VALUES (?,'nhan_xet',?,?, 'buoi_hoc',?,?) ON DUPLICATE KEY UPDATE noi_dung=VALUES(noi_dung)",
                    [$parent['phu_huynh_user_id'], 'Sổ buổi học · '.$session['ma_lop'], $session['ngay'].' · '.$data['chuDe'], $id, 'teacher-log:'.$id.':'.$student['hocVienId']]);
            }
            Store::recordAudit($actor['userId'], 'sua', 'buoi_hoc', $id, $before,
                ['thao_tac'=>'ghi_so_buoi_hoc', 'chu_de'=>$data['chuDe'], 'hoc_vien_ids'=>$received]);
        });
        return self::detail($id);
    }

    public static function request(array $body): array
    {
        $actor = AttendanceFilter::teacher();
        AttendanceValidator::fields($body, ['loai', 'buoiHocId', 'lyDo', 'ngayMoi', 'gioBatDau', 'gioKetThuc', 'checkinMoi', 'checkoutMoi']);
        $data = Input::validate($body, ['loai'=>[['nghi_day','doi_lich','dieu_chinh_cong'], true], 'buoiHocId'=>['id', true]]);
        $reason = AttendanceValidator::text($body['lyDo'] ?? null, 255, true);
        if ($data['loai'] === 'doi_lich') {
            $slot = Input::validate($body, ['ngayMoi'=>['date', true], 'gioBatDau'=>['time', true], 'gioKetThuc'=>['time', true]]);
            Input::pair($slot, 'gioBatDau', 'gioKetThuc');
            $now = Store::one('SELECT NOW() AS now')['now'];
            if ($slot['ngayMoi'].' '.$slot['gioBatDau'] <= $now) throw new ApiException(400, 'Ngày và giờ đề xuất phải sau thời điểm hiện tại.');
            $reason = '[Đổi lịch] '.$reason.' · Đề xuất '.$slot['ngayMoi'].' '.$slot['gioBatDau'].'–'.$slot['gioKetThuc'];
        }
        $checkin = AttendanceValidator::dateTime($body['checkinMoi'] ?? null, 'Check-in đề nghị');
        $checkout = AttendanceValidator::dateTime($body['checkoutMoi'] ?? null, 'Check-out đề nghị');
        if ($data['loai'] === 'dieu_chinh_cong' && (!$checkin || !$checkout)) throw new ApiException(400, 'Vui lòng nhập đủ ngày và giờ Check-in, Check-out đề nghị.');
        if ($data['loai'] === 'dieu_chinh_cong' && $checkout <= $checkin) throw new ApiException(400, 'Check-out đề nghị phải sau Check-in đề nghị.');
        return Store::transaction(function () use ($data, $actor, $reason, $checkin, $checkout) {
            $session = AttendanceRepository::lock($data['buoiHocId'], $actor['userId']);
            if ($session['vai_tro'] !== 'day_chinh') throw new ApiException(403, 'Ca này chưa phân công bạn làm giáo viên chính.');
            $type = $data['loai'];
            $now = Store::one('SELECT NOW() AS now')['now'];
            ShiftRequestPolicy::assertCanRequest(
                $session,
                $now,
                'day_chinh',
                $type === 'dieu_chinh_cong' ? 'time_adjustment' : 'reschedule'
            );
            if ($type === 'dieu_chinh_cong') {
                $table = 'dieu_chinh_cong';
                $values = ['phan_cong_id'=>$session['phan_cong_id'], 'loai'=>$session['checkin_luc'] ? 'sua_gio' : 'bo_sung_checkin',
                    'checkin_cu'=>$session['checkin_luc'], 'checkout_cu'=>$session['checkout_luc'],
                    'checkin_moi'=>$checkin, 'checkout_moi'=>$checkout, 'ly_do'=>$reason, 'nguoi_yeu_cau_id'=>$actor['userId']];
                $existing = Store::one("SELECT id FROM dieu_chinh_cong WHERE phan_cong_id=? AND trang_thai='cho_duyet'", [$session['phan_cong_id']]);
            } else {
                $table = $type === 'nghi_day' ? 'yeu_cau_nghi_lop' : 'don_xin_nghi';
                $values = ['buoi_hoc_id'=>$session['id'], 'nguoi_gui_id'=>$actor['userId'], 'ly_do'=>$reason];
                if ($type === 'doi_lich') $values += ['doi_tuong'=>'day_chinh'];
                $existing = Store::one("SELECT id FROM $table WHERE buoi_hoc_id=? AND nguoi_gui_id=? AND trang_thai='cho_duyet'", [$session['id'], $actor['userId']]);
            }
            if ($existing) throw new ApiException(409, 'Ca học đã có yêu cầu cùng loại chờ xử lý.');
            $id = Store::insert($table, $values);
            Store::recordAudit($actor['userId'], 'them', $table, $id, null, $values + ['loai_yeu_cau'=>$type]);
            AttendanceRepository::notify($session, $actor, $type, $id, $reason);
            return ['id'=>$id, 'trangThai'=>'cho_duyet'];
        });
    }
}
