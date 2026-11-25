<?php
namespace App\Modules\People\Features\Assistants;

use App\Modules\Academic\Features\Attendance\{SessionMaterialService, ShiftRequestPolicy};
use App\Shared\{ApiException, Input, Store};
use App\Modules\People\Features\Teachers\{TeachersRepository, TeachersService, TeachersValidator};

final class AssistantsService
{
    public static function workspace(): array
    {
        $actor = AssistantsFilter::assistant();
        $serverNow = Store::one('SELECT NOW() AS now')['now'];
        $profile = TeachersService::detail($actor['assistantId']);

        return AssistantsModel::map([
            'server_now' => $serverNow,
            'profile' => $profile,
            'sessions' => AssistantsRepository::sessions($actor['userId'], $serverNow),
            'classes' => AssistantsRepository::classes($actor['userId']),
            'requests' => AssistantsRepository::requests($actor['userId']),
            'periods' => AssistantsRepository::periods($actor['userId']),
            'month_summary' => AssistantsRepository::monthSummary($actor['userId'], $serverNow),
        ]);
    }

    public static function detail(int $id): array
    {
        $actor = AssistantsFilter::assistant();
        return AssistantsModel::map(AssistantsRepository::detail($id, $actor['userId']));
    }

    private static function writable(array $session): void
    {
        if (in_array($session['trang_thai'], ['huy', 'doi'], true)) {
            throw new ApiException(409, 'Ca học đã hủy hoặc đổi lịch.');
        }
        if ($session['ky_cong'] !== 'mo') {
            throw new ApiException(409, 'Kỳ công đã khóa. Vui lòng gửi yêu cầu để Admin xem xét.');
        }
        if ($session['vai_tro'] !== 'tro_giang') {
            throw new ApiException(403, 'Ca này chưa phân công bạn làm trợ giảng.');
        }
    }

    public static function clock(int $id, string $direction, array $body): array
    {
        AssistantsValidator::fields($body, []);
        $actor = AssistantsFilter::assistant();

        Store::transaction(function () use ($id, $direction, $actor) {
            $session = AssistantsRepository::lock($id, $actor['userId']);

            // Idempotent: replay returns current recorded timestamp
            if ($direction === 'in' && $session['checkin_luc'] !== null) return;
            if ($direction === 'out' && $session['checkout_luc'] !== null) return;

            self::writable($session);

            if ($direction === 'in') {
                if ($session['phan_cong_trang_thai'] === 'da_day') {
                    throw new ApiException(409, 'Ca hỗ trợ của bạn đã hoàn tất. Vui lòng gửi đề nghị điều chỉnh công.');
                }
                Store::execute("UPDATE phan_cong_buoi_hoc SET checkin_luc=NOW(), trang_thai_cong='cho_duyet' WHERE id=?",
                    [$session['phan_cong_id']]);
                // Trợ giảng KHÔNG cập nhật buoi_hoc.trang_thai
            } else {
                if ($session['checkin_luc'] === null) {
                    throw new ApiException(409, 'Bạn cần check-in trước.');
                }
                $now = Store::one('SELECT NOW() AS now')['now'];
                if ($now <= $session['checkin_luc']) {
                    throw new ApiException(409, 'Giờ check-out phải sau check-in. Vui lòng thử lại.');
                }
                Store::execute("UPDATE phan_cong_buoi_hoc SET checkout_luc=?, trang_thai='da_day', trang_thai_cong='hop_le' WHERE id=?",
                    [$now, $session['phan_cong_id']]);
                // Trợ giảng KHÔNG cập nhật buoi_hoc.trang_thai
            }

            Store::recordAudit($actor['userId'], 'sua', 'phan_cong_buoi_hoc', (int)$session['phan_cong_id'],
                ['checkin_luc' => $session['checkin_luc'], 'checkout_luc' => $session['checkout_luc']],
                ['thao_tac' => $direction === 'in' ? 'checkin' : 'checkout']);
        });

        return self::detail($id);
    }

    public static function saveLog(int $id, array $body): array
    {
        $actor = AssistantsFilter::assistant();
        $data = AssistantsValidator::log($body);

        Store::transaction(function () use ($id, $actor, $data) {
            $session = AssistantsRepository::lock($id, $actor['userId']);
            self::writable($session);

            if (!$session['checkin_luc']) {
                throw new ApiException(409, 'Bạn cần check-in trước khi ghi sổ buổi học.');
            }

            $before = AssistantsRepository::detail($id, $actor['userId']);
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
                $existing = Store::one('SELECT id, bai_tap_id FROM so_theo_doi_buoi_hoc WHERE buoi_hoc_id=? AND hoc_vien_id=?',
                    [$id, $student['hocVienId']]);
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
                ['thao_tac' => 'tro_giang_ghi_so_buoi_hoc', 'chu_de' => $data['chuDe'], 'hoc_vien_ids' => $received]);
        });

        return self::detail($id);
    }

    public static function saveAvailability(array $body, ?int $id = null): array
    {
        $actor = AssistantsFilter::assistant();
        AssistantsValidator::fields($body, ['thu', 'gioBatDau', 'gioKetThuc']);
        $slot = TeachersValidator::validate(['lichRanhs' => [$body]], false)['lichRanhs'][0];
        return TeachersRepository::availability($actor + ['teacherId' => $actor['assistantId']], $id, $slot);
    }

    public static function deleteAvailability(int $id): array
    {
        $actor = AssistantsFilter::assistant();
        return TeachersRepository::availability($actor + ['teacherId' => $actor['assistantId']], $id, null);
    }

    public static function attendance(int $id, array $body): array
    {
        $actor = AssistantsFilter::assistant();
        $data = AssistantsValidator::attendance($body);

        Store::transaction(function () use ($id, $actor, $data) {
            $session = AssistantsRepository::lock($id, $actor['userId']);
            self::writable($session);

            if (!$session['checkin_luc']) {
                throw new ApiException(409, 'Bạn cần check-in trước khi hỗ trợ điểm danh.');
            }

            $before = AssistantsRepository::detail($id, $actor['userId']);
            if (!hash_equals($before['revision'], $data['revision'])) {
                throw new ApiException(409, 'Sổ vừa thay đổi, tải lại trước khi lưu.');
            }

            $expected = array_map('intval', array_column($before['hocViens'], 'hoc_vien_id'));
            $received = array_column($data['hocViens'], 'hocVienId');
            sort($expected);
            sort($received);
            if ($expected !== $received) {
                throw new ApiException(409, 'Danh sách học viên đã thay đổi hoặc không thuộc ca học.');
            }

            foreach ($data['hocViens'] as $student) {
                $existing = Store::one('SELECT id, trang_thai_diem_danh FROM so_theo_doi_buoi_hoc
                    WHERE buoi_hoc_id=? AND hoc_vien_id=?', [$id, $student['hocVienId']]);
                if ($existing) {
                    Store::execute('UPDATE so_theo_doi_buoi_hoc SET trang_thai_diem_danh=? WHERE id=?',
                        [$student['trangThaiDiemDanh'], (int)$existing['id']]);
                } else {
                    Store::insert('so_theo_doi_buoi_hoc', [
                        'buoi_hoc_id' => $id,
                        'hoc_vien_id' => $student['hocVienId'],
                        'trang_thai_diem_danh' => $student['trangThaiDiemDanh'],
                        'nguoi_nhap_id' => $actor['userId'],
                    ]);
                }
            }

            Store::recordAudit($actor['userId'], 'sua', 'so_theo_doi_buoi_hoc', $id,
                ['truoc' => $before['hocViens']],
                ['thao_tac' => 'ho_tro_diem_danh', 'sau' => $data['hocViens']]);
        });

        return self::detail($id);
    }

    public static function request(array $body): array
    {
        $actor = AssistantsFilter::assistant();
        AssistantsValidator::fields($body, [
            'loai', 'buoiHocId', 'lyDo', 'ngayMoi', 'gioBatDau', 'gioKetThuc', 'checkinMoi', 'checkoutMoi'
        ]);

        $data = Input::validate($body, [
            'loai' => [['bao_ban', 'doi_gio', 'dieu_chinh_cong'], true],
            'buoiHocId' => ['id', true],
        ]);
        $rawReason = AssistantsValidator::text($body['lyDo'] ?? null, 255, true);
        $type = $data['loai'];

        if ($type === 'doi_gio') {
            $slot = Input::validate($body, [
                'ngayMoi' => ['date', true],
                'gioBatDau' => ['time', true],
                'gioKetThuc' => ['time', true],
            ]);
            Input::pair($slot, 'gioBatDau', 'gioKetThuc');
            $now = Store::one('SELECT NOW() AS now')['now'];
            if ($slot['ngayMoi'].' '.$slot['gioBatDau'] <= $now) {
                throw new ApiException(400, 'Ngày và giờ đề xuất phải sau thời điểm hiện tại.');
            }
            $reason = '[Đổi giờ] ' . $rawReason . ' · Đề xuất ' . $slot['ngayMoi'] . ' ' . $slot['gioBatDau'] . '–' . $slot['gioKetThuc'];
        } elseif ($type === 'bao_ban') {
            $reason = '[Báo bận] ' . $rawReason;
        } else {
            $reason = $rawReason;
        }

        $checkin = AssistantsValidator::dateTime($body['checkinMoi'] ?? null, 'Check-in đề nghị');
        $checkout = AssistantsValidator::dateTime($body['checkoutMoi'] ?? null, 'Check-out đề nghị');
        if ($type === 'dieu_chinh_cong') {
            if (!$checkin || !$checkout) {
                throw new ApiException(400, 'Vui lòng nhập đủ ngày và giờ Check-in, Check-out đề nghị.');
            }
            if ($checkout <= $checkin) {
                throw new ApiException(400, 'Check-out đề nghị phải sau Check-in đề nghị.');
            }
        }

        return Store::transaction(function () use ($data, $actor, $type, $reason, $checkin, $checkout) {
            $session = AssistantsRepository::lock($data['buoiHocId'], $actor['userId']);
            if ($session['vai_tro'] !== 'tro_giang') {
                throw new ApiException(403, 'Ca này chưa phân công bạn làm trợ giảng.');
            }

            $now = Store::one('SELECT NOW() AS now')['now'];
            ShiftRequestPolicy::assertCanRequest(
                $session,
                $now,
                'tro_giang',
                $type === 'dieu_chinh_cong' ? 'time_adjustment' : 'reschedule'
            );

            if ($type === 'dieu_chinh_cong') {
                $table = 'dieu_chinh_cong';
                $existing = Store::one("SELECT id FROM dieu_chinh_cong WHERE phan_cong_id=? AND trang_thai='cho_duyet'",
                    [$session['phan_cong_id']]);
                if ($existing) {
                    throw new ApiException(409, 'Ca học đã có yêu cầu cùng loại chờ xử lý.');
                }
                $values = [
                    'phan_cong_id' => $session['phan_cong_id'],
                    'loai' => $session['checkin_luc'] ? 'sua_gio' : 'bo_sung_checkin',
                    'checkin_cu' => $session['checkin_luc'],
                    'checkout_cu' => $session['checkout_luc'],
                    'checkin_moi' => $checkin,
                    'checkout_moi' => $checkout,
                    'ly_do' => $reason,
                    'nguoi_yeu_cau_id' => $actor['userId'],
                    'trang_thai' => 'cho_duyet',
                ];
            } else {
                $table = 'don_xin_nghi';
                $existing = Store::one("SELECT id FROM don_xin_nghi WHERE buoi_hoc_id=? AND nguoi_gui_id=? AND doi_tuong='tro_giang' AND trang_thai='cho_duyet'",
                    [$session['id'], $actor['userId']]);
                if ($existing) {
                    throw new ApiException(409, 'Ca học đã có đơn cùng loại chờ xử lý.');
                }
                $values = [
                    'buoi_hoc_id' => $session['id'],
                    'nguoi_gui_id' => $actor['userId'],
                    'doi_tuong' => 'tro_giang',
                    'ly_do' => $reason,
                    'trang_thai' => 'cho_duyet',
                ];
            }

            $id = Store::insert($table, $values);
            Store::recordAudit($actor['userId'], 'them', $table, $id, null, $values + ['loai_yeu_cau' => $type]);
            AssistantsRepository::notify($session, $actor, $type, $id, $reason);

            return [
                'id' => $id,
                'trangThai' => 'cho_duyet',
                'ma' => ($type === 'dieu_chinh_cong' ? 'DC-' : ($type === 'doi_gio' ? 'DG-' : 'BB-')) . $id,
            ];
        });
    }
}
