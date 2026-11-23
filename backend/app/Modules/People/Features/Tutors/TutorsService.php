<?php
namespace App\Modules\People\Features\Tutors;

use App\Modules\Academic\Features\Admissions\{AdmissionsModel, AdmissionsRepository, AdmissionsService};
use App\Modules\Academic\Features\Attendance\{SessionMaterialService, ShiftRequestPolicy};
use App\Modules\People\Features\Teachers\{TeachersRepository, TeachersService};
use App\Shared\{ApiException, Input, Store};
use CodeIgniter\HTTP\Files\UploadedFile;

final class TutorsService
{
    public static function workspace(): array
    {
        $actor = TutorsFilter::tutor();
        $now = Store::one('SELECT NOW() AS now')['now'];
        return TutorsModel::map([
            'server_now' => $now,
            'profile' => TeachersService::detail($actor['tutorId']),
            'sessions' => TutorsRepository::sessions($actor['userId'], $now),
            'classes' => TutorsRepository::classes($actor),
            'requests' => TutorsRepository::requests($actor['userId']),
            'periods' => TutorsRepository::periods($actor['userId']),
        ]);
    }

    public static function detail(int $id): array
    {
        $actor = TutorsFilter::tutor();
        return TutorsModel::map(TutorsRepository::detail($id, $actor['userId']));
    }

    public static function uploadMaterial(int $id, ?UploadedFile $file, array $meta): array
    {
        $actor = TutorsFilter::tutor();
        SessionMaterialService::saveMaterial(
            $id,
            $actor,
            static fn(int $sid, int $uid): array => TutorsRepository::lock($sid, $uid),
            $file,
            $meta
        );
        return self::detail($id);
    }

    public static function removeMaterial(int $id): array
    {
        $actor = TutorsFilter::tutor();
        SessionMaterialService::removeMaterial(
            $id,
            $actor,
            static fn(int $sid, int $uid): array => TutorsRepository::lock($sid, $uid)
        );
        return self::detail($id);
    }

    private static function writable(array $session): void
    {
        if (in_array($session['trang_thai'], ['huy', 'doi'], true)) {
            throw new ApiException(409, 'Ca học đã hủy hoặc đổi lịch.');
        }
        if ($session['ky_cong'] !== 'mo') {
            throw new ApiException(409, 'Kỳ công đã khóa. Vui lòng gửi yêu cầu để Admin xem xét.');
        }
        if ($session['vai_tro'] !== 'day_chinh') {
            throw new ApiException(403, 'Ca này chưa phân công bạn làm gia sư chính.');
        }
    }

    public static function clock(int $id, string $direction, array $body): array
    {
        TutorsValidator::fields($body, []);
        $actor = TutorsFilter::tutor();
        Store::transaction(function () use ($id, $direction, $actor) {
            $session = TutorsRepository::lock($id, $actor['userId']);
            if ($direction === 'in' && $session['checkin_luc'] !== null) return;
            if ($direction === 'out' && $session['checkout_luc'] !== null) return;
            self::writable($session);
            if ($direction === 'in') {
                if ($session['trang_thai'] === 'da_day') {
                    throw new ApiException(409, 'Ca học đã kết thúc. Gửi yêu cầu bổ sung công.');
                }
                Store::execute("UPDATE phan_cong_buoi_hoc SET checkin_luc=NOW(), trang_thai_cong='cho_duyet' WHERE id=?", [$session['phan_cong_id']]);
                Store::execute("UPDATE buoi_hoc SET trang_thai='dang_day' WHERE id=?", [$id]);
            } else {
                if ($session['checkin_luc'] === null) {
                    throw new ApiException(409, 'Bạn cần check-in trước.');
                }
                $now = Store::one('SELECT NOW() AS now')['now'];
                if ($now <= $session['checkin_luc']) {
                    throw new ApiException(409, 'Giờ check-out phải sau check-in. Vui lòng thử lại.');
                }
                Store::execute("UPDATE phan_cong_buoi_hoc SET checkout_luc=?, trang_thai='da_day', trang_thai_cong='hop_le' WHERE id=?", [$now, $session['phan_cong_id']]);
                Store::execute("UPDATE buoi_hoc SET trang_thai='da_day' WHERE id=?", [$id]);
            }
            Store::recordAudit($actor['userId'], 'sua', 'phan_cong_buoi_hoc', (int)$session['phan_cong_id'],
                ['checkin_luc' => $session['checkin_luc'], 'checkout_luc' => $session['checkout_luc']],
                ['thao_tac' => $direction === 'in' ? 'checkin' : 'checkout']);
        });
        return self::detail($id);
    }

    public static function saveLog(int $id, array $body): array
    {
        $actor = TutorsFilter::tutor();
        $data = TutorsValidator::log($body);
        Store::transaction(function () use ($id, $actor, $data) {
            $session = TutorsRepository::lock($id, $actor['userId']);
            self::writable($session);
            if (!$session['checkin_luc']) {
                throw new ApiException(409, 'Bạn cần check-in trước khi ghi sổ.');
            }
            $before = TutorsRepository::detail($id, $actor['userId']);
            if (!hash_equals($before['revision'], $data['revision'])) {
                throw new ApiException(409, 'Sổ buổi học vừa thay đổi. Tải lại trước khi lưu.');
            }
            $expected = array_map('intval', array_column($before['hocViens'], 'hoc_vien_id'));
            $received = array_column($data['hocViens'], 'hocVienId');
            sort($expected);
            sort($received);
            if ($expected !== $received) {
                throw new ApiException(409, 'Danh sách học viên đã thay đổi hoặc không thuộc ca học.');
            }
            if ($data['chuDe'] !== $before['chu_de_bai_hoc']
                || $data['noiDung'] !== $before['noi_dung_bai_hoc']) {
                Store::execute('UPDATE buoi_hoc SET chu_de_bai_hoc=?, noi_dung_bai_hoc=? WHERE id=?',
                    [$data['chuDe'], $data['noiDung'], $id]);
            }
            $requestedHomeworkId = isset($data['baiTapChamId']) && $data['baiTapChamId'] !== null
                ? (int)$data['baiTapChamId']
                : null;
            foreach ($data['hocViens'] as $student) {
                $existing = Store::one('SELECT id, bai_tap_id FROM so_theo_doi_buoi_hoc WHERE buoi_hoc_id=? AND hoc_vien_id=?', [$id, $student['hocVienId']]);
                $assignment = SessionMaterialService::resolveGradingHomeworkId(
                    $session,
                    $requestedHomeworkId,
                    $existing,
                    (int)$student['hocVienId'],
                    $student['diemBaiTapGiay'] !== null ? (float)$student['diemBaiTapGiay'] : null,
                    $student['nhanXetBaiTap']
                );
                $values = [
                    'trang_thai_diem_danh' => $student['trangThaiDiemDanh'],
                    'bai_tap_id' => $assignment,
                    'diem_bai_tap_giay' => $student['diemBaiTapGiay'],
                    'nhan_xet_bai_tap' => $student['nhanXetBaiTap'],
                    'muc_tiep_thu' => $student['mucTiepThu'],
                    'thai_do' => $student['thaiDo'],
                    'nhan_xet_buoi_hoc' => $student['nhanXetBuoiHoc'],
                    'huong_cai_thien' => $student['huongCaiThien'],
                    'nguoi_nhap_id' => $actor['userId'],
                ];
                if ($existing) {
                    Store::update('so_theo_doi_buoi_hoc', 'id', (int)$existing['id'], $values, array_combine(array_keys($values), array_keys($values)));
                } else {
                    Store::insert('so_theo_doi_buoi_hoc', ['buoi_hoc_id' => $id, 'hoc_vien_id' => $student['hocVienId']] + $values);
                }
                $parent = Store::one('SELECT phu_huynh_user_id FROM hoc_vien WHERE id=?', [$student['hocVienId']]);
                if ($parent && $parent['phu_huynh_user_id']) {
                    Store::execute("INSERT INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
                        VALUES (?,'nhan_xet',?,?,'buoi_hoc',?,?) ON DUPLICATE KEY UPDATE noi_dung=VALUES(noi_dung)",
                        [$parent['phu_huynh_user_id'], 'Sổ buổi học · '.$session['ma_lop'], $session['ngay'].' · '.$data['chuDe'], $id, 'teacher-log:'.$id.':'.$student['hocVienId']]);
                }
            }
            Store::recordAudit($actor['userId'], 'sua', 'buoi_hoc', $id, $before,
                ['thao_tac' => 'gia_su_ghi_so_buoi_hoc', 'chu_de' => $data['chuDe'], 'hoc_vien_ids' => $received]);
        });
        return self::detail($id);
    }

    public static function request(array $body): array
    {
        $actor = TutorsFilter::tutor();
        TutorsValidator::fields($body, ['loai', 'buoiHocId', 'lyDo', 'ngayMoi', 'gioBatDau', 'gioKetThuc', 'checkinMoi', 'checkoutMoi']);
        $data = Input::validate($body, ['loai' => [['nghi_day', 'doi_lich', 'dieu_chinh_cong'], true], 'buoiHocId' => ['id', true]]);
        $reason = TutorsValidator::text($body['lyDo'] ?? null, 255, true);
        if ($data['loai'] === 'doi_lich') {
            $slot = Input::validate($body, ['ngayMoi' => ['date', true], 'gioBatDau' => ['time', true], 'gioKetThuc' => ['time', true]]);
            Input::pair($slot, 'gioBatDau', 'gioKetThuc');
            $now = Store::one('SELECT NOW() AS now')['now'];
            if ($slot['ngayMoi'].' '.$slot['gioBatDau'] <= $now) {
                throw new ApiException(400, 'Ngày và giờ đề xuất phải sau thời điểm hiện tại.');
            }
            $reason = '[Đổi lịch] '.$reason.' · Đề xuất '.$slot['ngayMoi'].' '.$slot['gioBatDau'].'–'.$slot['gioKetThuc'];
        }
        $checkin = TutorsValidator::dateTime($body['checkinMoi'] ?? null, 'Check-in đề nghị');
        $checkout = TutorsValidator::dateTime($body['checkoutMoi'] ?? null, 'Check-out đề nghị');
        if ($data['loai'] === 'dieu_chinh_cong' && (!$checkin || !$checkout)) {
            throw new ApiException(400, 'Vui lòng nhập đủ ngày và giờ Check-in, Check-out đề nghị.');
        }
        if ($data['loai'] === 'dieu_chinh_cong' && $checkout <= $checkin) {
            throw new ApiException(400, 'Check-out đề nghị phải sau Check-in đề nghị.');
        }
        return Store::transaction(function () use ($data, $actor, $reason, $checkin, $checkout) {
            $session = TutorsRepository::lock($data['buoiHocId'], $actor['userId']);
            if ($session['vai_tro'] !== 'day_chinh') {
                throw new ApiException(403, 'Ca này chưa phân công bạn làm gia sư chính.');
            }
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
                $values = [
                    'phan_cong_id' => $session['phan_cong_id'],
                    'loai' => $session['checkin_luc'] ? 'sua_gio' : 'bo_sung_checkin',
                    'checkin_cu' => $session['checkin_luc'],
                    'checkout_cu' => $session['checkout_luc'],
                    'checkin_moi' => $checkin,
                    'checkout_moi' => $checkout,
                    'ly_do' => $reason,
                    'nguoi_yeu_cau_id' => $actor['userId'],
                ];
                $existing = Store::one("SELECT id FROM dieu_chinh_cong WHERE phan_cong_id=? AND trang_thai='cho_duyet'", [$session['phan_cong_id']]);
            } else {
                $table = $type === 'nghi_day' ? 'yeu_cau_nghi_lop' : 'don_xin_nghi';
                $values = ['buoi_hoc_id' => $session['id'], 'nguoi_gui_id' => $actor['userId'], 'ly_do' => $reason];
                if ($type === 'doi_lich') $values += ['doi_tuong' => 'day_chinh'];
                $existing = Store::one("SELECT id FROM $table WHERE buoi_hoc_id=? AND nguoi_gui_id=? AND trang_thai='cho_duyet'", [$session['id'], $actor['userId']]);
            }
            if ($existing) {
                throw new ApiException(409, 'Ca học đã có yêu cầu cùng loại chờ xử lý.');
            }
            $id = Store::insert($table, $values);
            Store::recordAudit($actor['userId'], 'them', $table, $id, null, $values + ['loai_yeu_cau' => $type]);
            TutorsRepository::notify($session, $actor, $type, $id, $reason);
            return ['id' => $id, 'trangThai' => 'cho_duyet'];
        });
    }

    public static function saveAvailability(array $body, ?int $id = null): array
    {
        $actor = TutorsFilter::tutor();
        $slot = TutorsValidator::availability($body);
        return TeachersRepository::availability($actor, $id, $slot);
    }

    public static function deleteAvailability(int $id): array
    {
        return TeachersRepository::availability(TutorsFilter::tutor(), $id, null);
    }

    private static function candidate(array $request, array $actor): ?array
    {
        if (!in_array($request['loai_lop_mong_muon'] ?? '', ['kem_1_1', 'kem_1_2'], true)) {
            return null;
        }
        $profiles = TeachersRepository::match([
            'monHocId' => $request['mon_hoc_id'],
            'capLopId' => $request['cap_lop_id'],
            'khuVucId' => $request['hinh_thuc'] === 'online' ? null : $request['khu_vuc_id'],
        ], 'gia_su');
        foreach ($profiles as $profile) {
            if ((int)$profile['id'] === $actor['tutorId']) {
                return AdmissionsRepository::candidate($request, AdmissionsRepository::slots((int)$request['id']), $profile);
            }
        }
        return null;
    }

    public static function opportunities(): array
    {
        $actor = TutorsFilter::tutor();
        $requests = Store::rows("SELECT y.*, m.ten AS ten_mon, cl.ten AS ten_cap_lop, kv.ten AS ten_khu_vuc
            FROM yeu_cau_hoc y
            JOIN mon_hoc m ON m.id=y.mon_hoc_id
            JOIN cap_lop cl ON cl.id=y.cap_lop_id
            LEFT JOIN khu_vuc kv ON kv.id=y.khu_vuc_id
            WHERE y.trang_thai IN ('moi','dang_tim','da_de_xuat')
              AND y.loai_lop_mong_muon IN ('kem_1_1','kem_1_2')
              AND EXISTS (SELECT 1 FROM nhan_vien nv WHERE nv.id=y.nhan_vien_id AND nv.trang_thai='dang_lam')
              AND (y.nguoi_gui_id IS NOT NULL OR EXISTS (SELECT 1 FROM hoc_vien hv WHERE hv.id=y.hoc_vien_id AND hv.deleted_at IS NULL))
              AND NOT EXISTS (SELECT 1 FROM de_xuat_nhan_su dx WHERE dx.yeu_cau_id=y.id AND dx.nhan_su_id=?)
            ORDER BY y.id DESC", [$actor['tutorId']]);
        $result = [];
        foreach ($requests as $request) {
            $parent = Store::one('SELECT phu_huynh_user_id FROM hoc_vien WHERE id=? AND deleted_at IS NULL', [$request['hoc_vien_id']]);
            if (!AdmissionsService::parentId($request + ['phu_huynh_user_id' => $parent['phu_huynh_user_id'] ?? null]) || !self::candidate($request, $actor)) {
                continue;
            }
            $result[] = AdmissionsModel::map([
                'id' => $request['id'],
                'ma_yc' => $request['ma_yc'],
                'loai_lop_mong_muon' => $request['loai_lop_mong_muon'],
                'ten_mon' => $request['ten_mon'],
                'ten_cap_lop' => $request['ten_cap_lop'],
                'hinh_thuc' => $request['hinh_thuc'],
                'ten_khu_vuc' => $request['ten_khu_vuc'],
                'muc_tieu' => $request['muc_tieu'],
                'lichYeuCau' => AdmissionsRepository::slots((int)$request['id']),
            ]);
        }
        return $result;
    }

    public static function applyOpportunity(int $id): array
    {
        $actor = TutorsFilter::tutor();
        return Store::transaction(function () use ($id, $actor) {
            $request = Store::one('SELECT * FROM yeu_cau_hoc WHERE id=? FOR UPDATE', [$id]);
            if (!$request || !in_array($request['trang_thai'], ['moi', 'dang_tim', 'da_de_xuat'], true)) {
                throw new ApiException(409, 'Yêu cầu học đã đóng.');
            }
            if (!in_array($request['loai_lop_mong_muon'] ?? '', ['kem_1_1', 'kem_1_2'], true)) {
                throw new ApiException(403, 'Gia sư chỉ được ứng tuyển yêu cầu lớp kèm gia sư (1:1 hoặc 1:2).', 'FORBIDDEN');
            }
            $existing = Store::one('SELECT id, tt_nhan_su FROM de_xuat_nhan_su WHERE yeu_cau_id=? AND nhan_su_id=?', [$id, $actor['tutorId']]);
            if ($existing) {
                return ['id' => $existing['id'], 'ttNhanSu' => $existing['tt_nhan_su']];
            }
            Store::one('SELECT id FROM nhan_su_chuyen_mon WHERE id=? FOR UPDATE', [$actor['tutorId']]);
            if (!self::candidate($request, $actor)) {
                throw new ApiException(409, 'Yêu cầu chưa khớp chuyên môn hoặc lịch rảnh hiện tại.');
            }
            $employee = Store::one("SELECT id FROM nhan_vien WHERE id=? AND trang_thai='dang_lam'", [$request['nhan_vien_id']]);
            if (!$employee) {
                throw new ApiException(409, 'Yêu cầu chưa có giáo vụ tiếp nhận.');
            }
            $parent = Store::one('SELECT phu_huynh_user_id FROM hoc_vien WHERE id=? AND deleted_at IS NULL', [$request['hoc_vien_id']]);
            $parentId = AdmissionsService::parentId($request + ['phu_huynh_user_id' => $parent['phu_huynh_user_id'] ?? null]);
            if (!$parentId) {
                throw new ApiException(409, 'Yêu cầu chưa liên kết người gửi để nhận phản hồi.');
            }
            $proposal = Store::insert('de_xuat_nhan_su', [
                'yeu_cau_id' => $id,
                'nhan_su_id' => $actor['tutorId'],
                'nhan_vien_id' => $employee['id'],
                'tt_nhan_su' => 'dong_y',
                'nhan_su_phan_hoi_luc' => Store::one('SELECT NOW() AS now')['now'],
                'ghi_chu' => 'Gia sư ứng tuyển nhận lớp kèm',
            ]);
            Store::execute("UPDATE yeu_cau_hoc SET trang_thai='da_de_xuat' WHERE id=?", [$id]);
            Store::recordAudit($actor['userId'], 'them', 'de_xuat_nhan_su', $proposal, null, ['thao_tac' => 'gia_su_ung_tuyen', 'yeu_cau_id' => $id]);
            $recipient = Store::one('SELECT user_id FROM nhan_vien WHERE id=?', [$employee['id']]);
            Store::insert('thong_bao', [
                'user_id' => $recipient['user_id'],
                'loai' => 'de_xuat',
                'tieu_de' => 'Gia sư ứng tuyển · '.$request['ma_yc'],
                'noi_dung' => 'Gia sư đã xác nhận nhu cầu nhận lớp kèm. Vui lòng theo dõi phản hồi phụ huynh.',
                'doi_tuong_loai' => 'de_xuat_nhan_su',
                'doi_tuong_id' => $proposal,
                'khoa_chong_trung' => 'tutor-apply:'.$proposal,
            ]);
            AdmissionsRepository::notify((int)$parentId, $proposal, 'Có gia sư nhận lớp '.$request['ma_yc'],
                'Vui lòng xem và phản hồi đề xuất DX-'.$proposal.'.', 'created');
            return ['id' => $proposal, 'ttNhanSu' => 'dong_y'];
        });
    }
}
