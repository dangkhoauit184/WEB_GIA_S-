<?php
namespace App\Modules\Academic\Features\Attendance;

use App\Shared\ApiException;

/**
 * Authoritative shift-request state machine shared by Teacher, Tutor, and Assistant portals.
 * All UI button visibility and backend request acceptance derive from this single policy.
 */
final class ShiftRequestPolicy
{
    public const PHASE_UNAUTHORIZED = 'unauthorized';
    public const PHASE_CANCELLED = 'cancelled';
    public const PHASE_UPCOMING = 'upcoming';
    public const PHASE_STARTED_UNCHECKED = 'started_not_checked_in';
    public const PHASE_IN_PROGRESS = 'in_progress';
    public const PHASE_COMPLETED = 'completed';

    /**
     * Compute real backend capabilities for a session based on DB state + server time.
     *
     * @param array<string, mixed> $session
     * @return array{trang_thai_ca:string, co_the_doi_lich:bool, co_the_chinh_cong:bool, ly_do_khoa:?string}
     */
    public static function capabilities(array $session, string $serverNow, string $expectedRole): array
    {
        $role = (string)($session['vai_tro'] ?? '');
        $assignmentStatus = (string)($session['phan_cong_trang_thai'] ?? 'da_phan_cong');
        if ($role !== $expectedRole || $assignmentStatus === 'da_go') {
            return [
                'trang_thai_ca' => self::PHASE_UNAUTHORIZED,
                'co_the_doi_lich' => false,
                'co_the_chinh_cong' => false,
                'ly_do_khoa' => 'Ca học không thuộc phạm vi phân công của bạn.',
            ];
        }

        $sessionStatus = (string)($session['trang_thai'] ?? '');
        if (in_array($sessionStatus, ['huy', 'doi'], true)) {
            return [
                'trang_thai_ca' => self::PHASE_CANCELLED,
                'co_the_doi_lich' => false,
                'co_the_chinh_cong' => false,
                'ly_do_khoa' => 'Ca học đã hủy hoặc đã đổi lịch.',
            ];
        }

        $hasCheckin = !empty($session['checkin_luc']);
        $hasCheckout = !empty($session['checkout_luc']);
        $startAt = (string)($session['ngay'] ?? '') . ' ' . (string)($session['gio_bat_dau'] ?? '00:00:00');
        $endAt = (string)($session['ngay'] ?? '') . ' ' . (string)($session['gio_ket_thuc'] ?? '00:00:00');

        // Checked-in but not yet checked-out => session in progress: hide & block BOTH request types.
        if ($hasCheckin && !$hasCheckout) {
            return [
                'trang_thai_ca' => self::PHASE_IN_PROGRESS,
                'co_the_doi_lich' => false,
                'co_the_chinh_cong' => false,
                'ly_do_khoa' => 'Ca học đang diễn ra, chưa hoàn tất Check-out. Không thể gửi yêu cầu đổi lịch hoặc chỉnh công.',
            ];
        }

        // Completed: checked out, or (for main teacher/tutor) session marked taught, or scheduled end time already passed.
        if (($hasCheckin && $hasCheckout) || ($expectedRole !== 'tro_giang' && $sessionStatus === 'da_day') || (!$hasCheckin && $endAt <= $serverNow)) {
            return [
                'trang_thai_ca' => self::PHASE_COMPLETED,
                'co_the_doi_lich' => false,
                'co_the_chinh_cong' => true,
                'ly_do_khoa' => null,
            ];
        }

        // Scheduled time started and not ended yet, user has not checked in yet.
        if (!$hasCheckin && $startAt <= $serverNow && $endAt > $serverNow) {
            return [
                'trang_thai_ca' => self::PHASE_STARTED_UNCHECKED,
                'co_the_doi_lich' => false,
                'co_the_chinh_cong' => false,
                'ly_do_khoa' => 'Ca học đã vào khung giờ. Vui lòng thực hiện Check-in trực tiếp hoặc chờ ca kết thúc.',
            ];
        }

        // Upcoming: not started yet and not checked in => only reschedule/leave allowed.
        return [
            'trang_thai_ca' => self::PHASE_UPCOMING,
            'co_the_doi_lich' => true,
            'co_the_chinh_cong' => false,
            'ly_do_khoa' => null,
        ];
    }

    /**
     * Backend gate for request submission.
     *
     * @param string $requestKind 'reschedule' (doi_lich/doi_gio/nghi_day/bao_ban) | 'time_adjustment' (dieu_chinh_cong)
     */
    public static function assertCanRequest(array $session, string $serverNow, string $expectedRole, string $requestKind): void
    {
        $caps = self::capabilities($session, $serverNow, $expectedRole);
        if ($caps['trang_thai_ca'] === self::PHASE_UNAUTHORIZED) {
            throw new ApiException(403, 'Bạn không có quyền gửi yêu cầu cho ca học này.', 'FORBIDDEN');
        }
        if ($caps['trang_thai_ca'] === self::PHASE_CANCELLED) {
            throw new ApiException(409, 'Ca học đã hủy hoặc đổi lịch, không thể gửi yêu cầu.');
        }

        if ($requestKind === 'reschedule') {
            if (!$caps['co_the_doi_lich']) {
                if ($caps['trang_thai_ca'] === self::PHASE_IN_PROGRESS) {
                    throw new ApiException(409, 'Ca học đang diễn ra, không thể gửi yêu cầu đổi lịch hoặc xin nghỉ.');
                }
                if ($caps['trang_thai_ca'] === self::PHASE_COMPLETED) {
                    throw new ApiException(409, 'Ca học đã hoàn tất, không thể gửi yêu cầu đổi lịch hoặc xin nghỉ.');
                }
                throw new ApiException(409, 'Chỉ gửi yêu cầu đổi lịch hoặc xin nghỉ cho ca chưa bắt đầu và chưa check-in.');
            }
            return;
        }

        if ($requestKind === 'time_adjustment') {
            if (!$caps['co_the_chinh_cong']) {
                if ($caps['trang_thai_ca'] === self::PHASE_IN_PROGRESS) {
                    throw new ApiException(409, 'Ca học đang diễn ra và chưa hoàn tất Check-out. Không thể gửi yêu cầu chỉnh công lúc này.');
                }
                if ($caps['trang_thai_ca'] === self::PHASE_UPCOMING) {
                    throw new ApiException(409, 'Ca học chưa bắt đầu nên chưa thể gửi yêu cầu chỉnh công.');
                }
                throw new ApiException(409, 'Ca học đang trong khung giờ và chưa hoàn tất. Vui lòng hoàn tất ca trước khi gửi yêu cầu chỉnh công.');
            }
            return;
        }

        throw new ApiException(400, 'Loại yêu cầu không hợp lệ.');
    }
}
