<?php
namespace App\Modules\Academic\Features\AcademicAffairs;

use App\Shared\{ApiException, Input, Store};
use App\Modules\Academic\Features\Admissions\AdmissionsService;
use App\Modules\People\Features\Students\StudentsService;
use App\Modules\Finance\Features\Tuition\TuitionService;

final class AcademicAffairsService
{
    public static function summary(): array
    {
        $actor = AcademicAffairsFilter::check();
        $data = AcademicAffairsRepository::summary($actor);
        $data['requests'] = ['items'=>[], 'total'=>0];
        $data['invoices'] = [];
        if (AcademicAffairsFilter::allows($actor, 'TiepNhanYeuCau')) {
            foreach (['moi','dang_tim'] as $status) {
                $page = AdmissionsService::requests(['perPage'=>5, 'trangThai'=>$status]);
                $data['requests']['total'] += $page['total'];
                $data['requests']['items'] = array_merge($data['requests']['items'], $page['items']);
            }
            usort($data['requests']['items'], static fn($left,$right)=>$right['id']<=>$left['id']);
            $data['requests']['items'] = array_slice($data['requests']['items'], 0, 5);
        }
        if (AcademicAffairsFilter::allows($actor, 'HocPhiThanhToan')) $data['invoices'] = array_values(array_filter(TuitionService::invoices(),
            static fn($row)=>!in_array($row['trangThai'], ['da_thanh_toan', 'huy'], true)));
        return $data;
    }

    public static function people(): array
    {
        AcademicAffairsFilter::check();
        return AcademicAffairsRepository::people();
    }

    public static function courses(): array
    {
        AcademicAffairsFilter::check('QuanLyLopHoc');
        return Store::rows('SELECT id,ma,ten,hoc_phi FROM khoa_hoc WHERE is_active=1 ORDER BY ten');
    }

    public static function registrations(int $studentId): array
    {
        AcademicAffairsFilter::check('QuanLyLopHoc');
        StudentsService::detail($studentId);
        return AcademicAffairsRepository::registrations($studentId);
    }

    public static function register(int $studentId, array $body): array
    {
        $actor = AcademicAffairsFilter::check('QuanLyLopHoc', 'add');
        $courseId = AcademicAffairsValidator::registration($body);
        StudentsService::detail($studentId);
        return Store::transaction(function () use ($studentId, $courseId, $actor) {
            Store::one('SELECT id FROM hoc_vien WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$studentId]);
            $course = Store::one('SELECT * FROM khoa_hoc WHERE id=? AND is_active=1 FOR UPDATE', [$courseId]);
            if (!$course) throw new ApiException(400, 'Khóa học đã ngừng nhận đăng ký.');
            $existing = Store::one("SELECT * FROM dang_ky_khoa_hoc WHERE hoc_vien_id=? AND khoa_hoc_id=? AND trang_thai IN ('cho_xep_lop','dang_hoc') FOR UPDATE", [$studentId, $courseId]);
            if ($existing) return $existing;
            $id = Store::insert('dang_ky_khoa_hoc', ['hoc_vien_id'=>$studentId, 'khoa_hoc_id'=>$courseId, 'hoc_phi_snapshot'=>$course['hoc_phi']]);
            $after = Store::one('SELECT * FROM dang_ky_khoa_hoc WHERE id=?', [$id]);
            Store::recordAudit((int)$actor['userId'], 'them', 'dang_ky_khoa_hoc', $id, null, $after);
            return $after;
        });
    }

    public static function student(array $body): array
    {
        AcademicAffairsFilter::check('QuanLyLopHoc', 'add');
        $courseId = $body['khoaHocId'] ?? null;
        unset($body['khoaHocId']);
        return Store::transaction(function () use ($body, $courseId) {
            $student = StudentsService::create($body);
            if ($courseId !== null) self::register((int)$student['id'], ['khoaHocId'=>$courseId]);
            return $student;
        });
    }

    public static function notifications(): array
    {
        return AcademicAffairsRepository::notifications((int)AcademicAffairsFilter::check()['userId']);
    }

    public static function readNotification(int $id, array $body): array
    {
        $actor = AcademicAffairsFilter::check();
        if ($body !== []) Input::fail('fields');
        $row = Store::one('SELECT id FROM thong_bao WHERE id=? AND user_id=?', [$id, $actor['userId']]);
        if (!$row) throw new ApiException(404, 'Không tìm thấy thông báo.');
        Store::execute('UPDATE thong_bao SET doc_luc=COALESCE(doc_luc,NOW()) WHERE id=? AND user_id=?', [$id, $actor['userId']]);
        return ['read'=>true];
    }
}
