<?php
namespace App\Modules\People\Features\Assistants;

use App\Modules\Academic\Features\Attendance\{SessionMaterialService, ShiftRequestPolicy};
use App\Shared\{ApiException, Store};

final class AssistantsRepository
{
    private const SESSION_SELECT = 'SELECT b.id, b.lop_id, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, b.loai, b.pham_vi_bu,
        b.trang_thai, b.chu_de_bai_hoc, b.noi_dung_bai_hoc, b.file_bai_giang_url, b.updated_at,
        l.ma_lop, l.ten_lop, l.hinh_thuc, l.dia_diem, l.link_hoc, COALESCE(b.phong_hoc, l.phong_hoc) AS phong_hoc,
        cs.ten_co_so, m.ten AS ten_mon, cl.ten AS ten_cap_lop,
        pc.id AS phan_cong_id, pc.vai_tro, pc.trang_thai AS phan_cong_trang_thai,
        pc.checkin_luc, pc.checkout_luc, pc.checkin_tre, pc.so_phut_thuc_te, pc.so_phut_tinh_cong, pc.trang_thai_cong,
        pc.gio_lich_bat_dau, pc.gio_lich_ket_thuc,
        u_gv.hoten AS ten_giao_vien_chinh,
        (SELECT COUNT(*) FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=b.id) AS so_hv_hoc_bu,
        (SELECT COUNT(*) FROM lop_hoc_vien x JOIN hoc_vien hv ON hv.id=x.hoc_vien_id
            WHERE x.lop_id=l.id AND hv.deleted_at IS NULL AND x.ngay_vao<=b.ngay
            AND (x.ngay_roi IS NULL OR x.ngay_roi>=b.ngay) AND x.trang_thai IN (\'dang_hoc\',\'hoc_thu\')) AS si_so_lop,
        COALESCE((SELECT bc.trang_thai FROM bang_cong_thang bc WHERE bc.user_id=pc.user_id
            AND bc.nam=YEAR(b.ngay) AND bc.thang=MONTH(b.ngay)), \'mo\') AS ky_cong
        FROM buoi_hoc b
        JOIN lop_hoc l ON l.id=b.lop_id
        JOIN phan_cong_buoi_hoc pc ON pc.buoi_hoc_id=b.id AND pc.trang_thai<>\'da_go\' AND pc.vai_tro=\'tro_giang\'
        LEFT JOIN phan_cong_buoi_hoc pc_gv ON pc_gv.buoi_hoc_id=b.id AND pc_gv.trang_thai<>\'da_go\' AND pc_gv.vai_tro=\'day_chinh\'
        LEFT JOIN users u_gv ON u_gv.user_id=pc_gv.user_id
        LEFT JOIN co_so cs ON cs.id=COALESCE(b.co_so_id, l.co_so_id)
        JOIN mon_hoc m ON m.id=l.mon_hoc_id
        JOIN cap_lop cl ON cl.id=l.cap_lop_id
        WHERE l.deleted_at IS NULL AND pc.user_id=?';

    public static function sessions(int $user, ?string $serverNow = null): array
    {
        $now = $serverNow ?? Store::one('SELECT NOW() AS now')['now'];
        $rows = Store::rows(self::SESSION_SELECT.' ORDER BY b.ngay, b.gio_bat_dau, b.id', [$user]);
        foreach ($rows as &$row) {
            $row['quyen_yeu_cau'] = ShiftRequestPolicy::capabilities($row, $now, 'tro_giang');
        }
        return $rows;
    }

    public static function session(int $id, int $user): array
    {
        return Store::one(self::SESSION_SELECT.' AND b.id=?', [$user, $id])
            ?? throw new ApiException(404, 'Bạn chưa được phân công ca học này hoặc ca học không tồn tại.');
    }

    public static function lock(int $id, int $user): array
    {
        Store::one('SELECT user_id FROM users WHERE user_id=? FOR UPDATE', [$user]);
        Store::one('SELECT id FROM buoi_hoc WHERE id=? FOR UPDATE', [$id]);
        Store::one('SELECT id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND user_id=? AND vai_tro=\'tro_giang\' FOR UPDATE', [$id, $user]);
        return self::session($id, $user);
    }

    public static function classes(int $user): array
    {
        $classes = Store::rows("SELECT l.id, l.ma_lop, l.ten_lop, l.trang_thai, l.hinh_thuc, l.dia_diem, l.phong_hoc, l.link_hoc,
            l.ngay_bat_dau, l.ngay_ket_thuc, k.ten AS ten_khoa, m.ten AS ten_mon, cl.ten AS ten_cap_lop,
            cs.ten_co_so, u_gv.hoten AS ten_giao_vien_chinh,
            (SELECT COUNT(*) FROM lop_hoc_vien x JOIN hoc_vien hv ON hv.id=x.hoc_vien_id
                WHERE x.lop_id=l.id AND hv.deleted_at IS NULL AND x.ngay_vao<=CURDATE()
                AND (x.ngay_roi IS NULL OR x.ngay_roi>=CURDATE()) AND x.trang_thai IN ('dang_hoc','hoc_thu')) AS si_so
            FROM lop_hoc l
            LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
            LEFT JOIN co_so cs ON cs.id=l.co_so_id
            JOIN mon_hoc m ON m.id=l.mon_hoc_id
            JOIN cap_lop cl ON cl.id=l.cap_lop_id
            LEFT JOIN nhan_su_chuyen_mon ns_gv ON ns_gv.id=l.nguoi_day_chinh_id
            LEFT JOIN users u_gv ON u_gv.user_id=ns_gv.user_id
            WHERE l.deleted_at IS NULL AND EXISTS
                (SELECT 1 FROM buoi_hoc b JOIN phan_cong_buoi_hoc pc ON pc.buoi_hoc_id=b.id
                WHERE b.lop_id=l.id AND pc.user_id=? AND pc.trang_thai<>'da_go' AND pc.vai_tro='tro_giang')
            ORDER BY l.id DESC", [$user]);

        foreach ($classes as &$class) {
            $class['lichHocs'] = Store::rows('SELECT thu, gio_bat_dau, gio_ket_thuc, hieu_luc_tu, hieu_luc_den
                FROM lich_hoc WHERE lop_id=? ORDER BY thu, gio_bat_dau', [$class['id']]);
            // Danh sách học viên: CHỈ gồm id, ma_hv, ho_ten, trang_thai (KHÔNG thông tin liên hệ gia đình)
            $class['hocViens'] = Store::rows("SELECT hv.id, hv.ma_hv, hv.ho_ten, x.trang_thai, x.ngay_vao, x.ngay_roi
                FROM lop_hoc_vien x JOIN hoc_vien hv ON hv.id=x.hoc_vien_id
                WHERE x.lop_id=? AND hv.deleted_at IS NULL ORDER BY hv.ho_ten, hv.id", [$class['id']]);
        }
        return $classes;
    }

    public static function students(array $session): array
    {
        return Store::rows("SELECT hv.id AS hoc_vien_id, hv.ma_hv, hv.ho_ten, s.id AS so_id,
            s.trang_thai_diem_danh, s.bai_tap_id, s.diem_bai_tap_giay, s.nhan_xet_bai_tap,
            s.muc_tiep_thu, s.thai_do, s.nhan_xet_buoi_hoc, s.huong_cai_thien, s.updated_at,
            IF(EXISTS(SELECT 1 FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=? AND hb.hoc_vien_id=hv.id), 1, 0) AS co_hoc_bu
            FROM lop_hoc_vien x JOIN hoc_vien hv ON hv.id=x.hoc_vien_id
            LEFT JOIN so_theo_doi_buoi_hoc s ON s.buoi_hoc_id=? AND s.hoc_vien_id=hv.id
            WHERE x.lop_id=? AND hv.deleted_at IS NULL AND x.ngay_vao<=? AND (x.ngay_roi IS NULL OR x.ngay_roi>=?)
            AND x.trang_thai<>'cho_xep_lop' AND (?<>'bu' OR ?<>'ca_nhan' OR EXISTS
                (SELECT 1 FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=? AND hb.hoc_vien_id=hv.id))
            ORDER BY hv.ho_ten, hv.id",
            [$session['id'], $session['id'], $session['lop_id'], $session['ngay'], $session['ngay'],
             $session['loai'], $session['pham_vi_bu'], $session['id']]);
    }

    public static function detail(int $id, int $user): array
    {
        $now = Store::one('SELECT NOW() AS now')['now'];
        $session = self::session($id, $user);
        $students = self::students($session);
        $homework = Store::one('SELECT id, tieu_de, noi_dung, file_de_bai_url, han_nop, updated_at FROM bai_tap WHERE buoi_hoc_id=? ORDER BY id DESC LIMIT 1', [$id]);
        $prev = SessionMaterialService::previousHomeworks($session);
        $session['hocViens'] = $students;
        $session['baiTap'] = $homework;
        $session['tai_lieu'] = SessionMaterialService::describe($session, $homework);
        $session['bai_tap_buoi_truoc'] = $prev['latest'];
        $session['danh_sach_bai_tap_truoc'] = $prev['items'];
        $session['quyen_yeu_cau'] = ShiftRequestPolicy::capabilities($session, $now, 'tro_giang');
        $session['revision'] = hash('sha256', json_encode([
            $session['chu_de_bai_hoc'],
            $session['noi_dung_bai_hoc'],
            $session['file_bai_giang_url'],
            $students,
            $homework,
        ], JSON_THROW_ON_ERROR));
        return $session;
    }

    public static function requests(int $user): array
    {
        return Store::rows("SELECT CONCAT(IF(r.ly_do LIKE '[Đổi giờ]%', 'DG-', 'BB-'), r.id) AS ma,
                IF(r.ly_do LIKE '[Đổi giờ]%', 'doi_gio', 'bao_ban') AS loai,
                b.id AS buoi_hoc_id, l.ma_lop, l.ten_lop, b.ngay, r.ly_do, r.trang_thai, r.ly_do_tu_choi AS phan_hoi, r.duyet_luc, r.created_at
            FROM don_xin_nghi r
            JOIN buoi_hoc b ON b.id=r.buoi_hoc_id
            JOIN lop_hoc l ON l.id=b.lop_id
            WHERE r.nguoi_gui_id=? AND r.doi_tuong='tro_giang'
            UNION ALL
            SELECT CONCAT('DC-', r.id) AS ma, 'dieu_chinh_cong' AS loai,
                b.id AS buoi_hoc_id, l.ma_lop, l.ten_lop, b.ngay, r.ly_do, r.trang_thai, r.ghi_chu_duyet AS phan_hoi, r.duyet_luc, r.created_at
            FROM dieu_chinh_cong r
            JOIN phan_cong_buoi_hoc pc ON pc.id=r.phan_cong_id
            JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
            JOIN lop_hoc l ON l.id=b.lop_id
            WHERE r.nguoi_yeu_cau_id=? AND pc.user_id=?
            ORDER BY created_at DESC, ma DESC", [$user, $user, $user]);
    }

    public static function periods(int $user): array
    {
        return Store::rows('SELECT nam, thang, trang_thai FROM bang_cong_thang WHERE user_id=? ORDER BY nam DESC, thang DESC', [$user]);
    }

    public static function monthSummary(int $user, string $serverNow): array
    {
        $year = (int)date('Y', strtotime($serverNow));
        $month = (int)date('n', strtotime($serverNow));

        $stats = Store::one("SELECT
            COALESCE(SUM(IF(pc.trang_thai_cong<>'tu_choi', pc.so_phut_tinh_cong, 0)), 0) AS tong_phut_cong,
            COUNT(IF(pc.checkout_luc IS NOT NULL, 1, NULL)) AS so_ca_hoan_thanh,
            COUNT(IF(pc.checkin_tre = 1, 1, NULL)) AS so_ca_tre
            FROM phan_cong_buoi_hoc pc
            JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
            WHERE pc.user_id=? AND pc.vai_tro='tro_giang' AND pc.trang_thai<>'da_go'
            AND YEAR(b.ngay)=? AND MONTH(b.ngay)=?", [$user, $year, $month]);

        $period = Store::one("SELECT trang_thai FROM bang_cong_thang
            WHERE user_id=? AND nam=? AND thang=?", [$user, $year, $month]);

        return [
            'nam' => $year,
            'thang' => $month,
            'tongPhutCong' => (int)($stats['tong_phut_cong'] ?? 0),
            'soCaHoanThanh' => (int)($stats['so_ca_hoan_thanh'] ?? 0),
            'soCaTre' => (int)($stats['so_ca_tre'] ?? 0),
            'kyCong' => $period['trang_thai'] ?? 'mo',
        ];
    }

    public static function notify(array $session, array $actor, string $type, int $id, string $reason): void
    {
        $admin = $type === 'dieu_chinh_cong';
        $staff = Store::rows("SELECT DISTINCT a.user_id FROM account a JOIN role_group g ON g.group_id=a.group_id
            LEFT JOIN nhan_vien nv ON nv.user_id=a.user_id
            WHERE a.status='active' AND a.is_deleted=0 AND g.is_deleted=0 AND
            ((? = 1 AND g.group_name='Admin') OR (? = 0 AND g.group_name='NhanVien' AND nv.trang_thai='dang_lam'
                AND (nv.co_so_id IS NULL OR nv.co_so_id=(SELECT co_so_id FROM lop_hoc WHERE id=?))))",
            [(int)$admin, (int)$admin, $session['lop_id']]);

        $labels = [
            'bao_ban' => 'Đơn báo bận ca học',
            'doi_gio' => 'Đề nghị đổi giờ ca học',
            'dieu_chinh_cong' => 'Đề nghị điều chỉnh công'
        ];
        foreach ($staff as $recipient) {
            Store::insert('thong_bao', [
                'user_id' => $recipient['user_id'],
                'loai' => 'don_nghi',
                'tieu_de' => ($labels[$type] ?? 'Đơn từ') . ' · ' . $session['ma_lop'],
                'noi_dung' => mb_substr(($actor['hoTen'] ?? 'Trợ giảng') . ' · ' . $session['ngay'] . ' · ' . $reason, 0, 1000),
                'doi_tuong_loai' => $type,
                'doi_tuong_id' => $id,
                'khoa_chong_trung' => 'assistant-request:' . $type . ':' . $id . ':' . $recipient['user_id']
            ]);
        }
    }
}
