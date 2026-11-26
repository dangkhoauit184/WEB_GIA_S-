<?php
namespace App\Modules\People\Features\Parents;

use App\Shared\{Access, Store};

final class ParentsRepository
{
    private const SELECT='SELECT u.*, kv.ten AS ten_khu_vuc, a.account_id, a.username, a.status AS account_status,
        (SELECT COUNT(*) FROM hoc_vien hv WHERE hv.phu_huynh_user_id=u.user_id AND hv.deleted_at IS NULL) AS so_con
        FROM users u LEFT JOIN account a ON a.user_id=u.user_id AND a.is_deleted=0
        LEFT JOIN role_group g ON g.group_id=a.group_id LEFT JOIN khu_vuc kv ON kv.id=u.khu_vuc_id WHERE u.is_deleted=0';
    private const FIELDS=['hoTen'=>'hoten','email'=>'email','sdt'=>'sdt','ngaySinh'=>'ngaysinh',
        'diaChi'=>'diachi','ngheNghiep'=>'nghe_nghiep','khuVucId'=>'khu_vuc_id'];

    public static function all(array $filters,array $user,?int $id=null): array
    {
        $sql=self::SELECT; $params=[];
        $sql.=" AND (g.group_name='PhuHuynh' OR EXISTS (SELECT 1 FROM hoc_vien h WHERE h.phu_huynh_user_id=u.user_id AND h.deleted_at IS NULL)
            OR EXISTS (SELECT 1 FROM audit_log al WHERE al.bang='users' AND al.ban_ghi_id=u.user_id AND JSON_UNQUOTE(JSON_EXTRACT(al.du_lieu_moi,'$.action'))='parent_profile_created'))";
        if ($id !== null) { $sql.=' AND u.user_id=?'; $params[]=$id; }
        if ($user['role'] === 'GIAO_VU' && $user['coSoId']) {
            $childScope=\App\Modules\Academic\Features\Admissions\AdmissionsFilter::studentScope('h.id', (int)$user['coSoId']);
            $sql.=" AND (NOT EXISTS (SELECT 1 FROM hoc_vien h WHERE h.phu_huynh_user_id=u.user_id AND h.deleted_at IS NULL)
                OR EXISTS (SELECT 1 FROM hoc_vien h WHERE h.phu_huynh_user_id=u.user_id AND h.deleted_at IS NULL $childScope))";
        }
        if (!Access::staff($user)) { $sql.=' AND u.user_id=?'; $params[]=$user['userId']; }
        if (!empty($filters['q'])) {
            $sql.=' AND (u.hoten LIKE ? OR u.sdt LIKE ? OR u.email LIKE ?)';
            array_push($params,...array_fill(0,3,'%'.$filters['q'].'%'));
        }
        if (!empty($filters['khuVucId'])) { $sql.=' AND u.khu_vuc_id=?'; $params[]=$filters['khuVucId']; }
        return Store::rows($sql.' ORDER BY u.user_id DESC',$params);
    }

    public static function children(int $id, array $user): array
    {
        $scope=$user['role']==='GIAO_VU' && $user['coSoId'] ? \App\Modules\Academic\Features\Admissions\AdmissionsFilter::studentScope('hv.id', (int)$user['coSoId']) : '';
        return Store::rows('SELECT hv.id,hv.ma_hv AS maHv,hv.ho_ten AS hoTen,hv.ngay_sinh AS ngaySinh,
            hv.gioi_tinh AS gioiTinh,hv.truong,hv.cap_lop_id AS capLopId,cl.ten AS tenCapLop,hv.trang_thai AS trangThai
            FROM hoc_vien hv LEFT JOIN cap_lop cl ON cl.id=hv.cap_lop_id
            WHERE hv.phu_huynh_user_id=? AND hv.deleted_at IS NULL'.$scope.' ORDER BY hv.id',[$id]);
    }

    public static function create(array $data, int $actor): int
    {
        return Store::transaction(function() use($data,$actor) {
            $values=[];
            foreach(self::FIELDS as $key=>$column) $values[$column]=$data[$key];
            $id=Store::insert('users',$values);
            Store::recordAudit($actor,'them','users',$id,null,['action'=>'parent_profile_created','hoTen'=>$data['hoTen']]);
            if ($data['username'] !== null) {
                $group=Store::one("SELECT group_id FROM role_group WHERE group_name='PhuHuynh' AND is_deleted=0");
                Store::insert('account',['user_id'=>$id,'group_id'=>$group['group_id'],'username'=>$data['username'],
                    'password_hash'=>password_hash($data['password'],PASSWORD_BCRYPT),'status'=>'active']);
            }
            return $id;
        });
    }

    public static function update(int $id,array $data): void
    {
        Store::update('users','user_id',$id,$data,self::FIELDS,'AND is_deleted=0');
    }

    public static function publicLanding(): array
    {
        $courses = Store::rows("SELECT k.id, k.ma, k.ten, k.mo_ta AS moTa, k.hoc_phi AS hocPhi
            FROM khoa_hoc k
            WHERE k.is_active=1
            ORDER BY k.id ASC");

        $subjects = Store::rows("SELECT m.id, m.ma, m.ten
            FROM mon_hoc m
            WHERE m.is_active=1
            ORDER BY m.id ASC");

        $gradeLevels = Store::rows("SELECT cl.id, cl.ten, cl.cap_hoc AS capHoc
            FROM cap_lop cl
            ORDER BY cl.id ASC");

        $campuses = Store::rows("SELECT cs.id, cs.ten_co_so AS tenCoSo, cs.dia_chi AS diaChi,
                cs.so_dien_thoai AS soDienThoai, cs.email, kv.ten AS tenKhuVuc
            FROM co_so cs
            LEFT JOIN khu_vuc kv ON kv.id=cs.khu_vuc_id
            WHERE cs.is_active=1
            ORDER BY cs.id ASC");

        // Strictly public teaching profile fields only (never email, phone, CCCD, bank, or pay rates).
        $teachers = Store::rows("SELECT ns.id, ns.phan_loai AS phanLoai,
                ns.don_vi_cong_tac AS donViCongTac,
                ns.gioi_thieu AS gioiThieu, ns.kinh_nghiem_nam AS kinhNghiemNam,
                ns.hinh_thuc_day AS hinhThucDay, u.hoten AS hoTen,
                (SELECT GROUP_CONCAT(DISTINCT m.ten ORDER BY m.id SEPARATOR ', ')
                 FROM nhan_su_mon nsm JOIN mon_hoc m ON m.id=nsm.mon_hoc_id AND m.is_active=1
                 WHERE nsm.nhan_su_id=ns.id) AS chuyenMon
            FROM nhan_su_chuyen_mon ns
            JOIN users u ON u.user_id=ns.user_id AND u.is_deleted=0
            WHERE ns.deleted_at IS NULL AND ns.trang_thai='hoat_dong'
            ORDER BY ns.kinh_nghiem_nam DESC, ns.id ASC");

        $stats = [
            'courses' => count($courses),
            'subjects' => count($subjects),
            'campuses' => count($campuses),
            'teachers' => count($teachers),
        ];

        return [
            'courses' => $courses,
            'subjects' => $subjects,
            'gradeLevels' => $gradeLevels,
            'campuses' => $campuses,
            'teachers' => $teachers,
            'stats' => $stats,
        ];
    }

    public static function createPublicConsultation(array $data): array
    {
        return Store::transaction(function () use ($data) {
            $monHocId = $data['monHocId'] ?? null;
            if (!$monHocId) {
                $mon = Store::one('SELECT id FROM mon_hoc WHERE is_active=1 ORDER BY id ASC LIMIT 1');
                $monHocId = $mon ? (int)$mon['id'] : null;
            }
            $capLopId = $data['capLopId'] ?? null;
            if (!$capLopId) {
                $cap = Store::one('SELECT id FROM cap_lop ORDER BY id ASC LIMIT 1');
                $capLopId = $cap ? (int)$cap['id'] : null;
            }
            if (!$monHocId || !$capLopId) {
                throw new \App\Shared\ApiException(400, 'Danh mục môn học / khối lớp chưa được khởi tạo trên hệ thống.');
            }

            $maYc = 'YC-' . strtoupper(bin2hex(random_bytes(5)));
            $mucTieu = !empty($data['khoaHocQuanTam'])
                ? 'Đăng ký tư vấn từ Website: ' . $data['khoaHocQuanTam']
                : 'Đăng ký tư vấn lộ trình từ Website';

            $id = Store::insert('yeu_cau_hoc', [
                'ma_yc' => $maYc,
                'hoc_vien_id' => null,
                'ho_ten_lien_he' => $data['hoTenLienHe'],
                'sdt_lien_he' => $data['sdtLienHe'],
                'email_lien_he' => $data['emailLienHe'] ?? null,
                'mon_hoc_id' => $monHocId,
                'cap_lop_id' => $capLopId,
                'loai_lop_mong_muon' => 'kem_1_1',
                'hinh_thuc' => 'ca_hai',
                'muc_tieu' => $mucTieu,
                'ghi_chu' => $data['ghiChu'] ?? null,
                'nguon' => 'website',
                'trang_thai' => 'moi',
                'nhan_vien_id' => null,
            ]);

            return [
                'id' => $id,
                'maYc' => $maYc,
                'trangThai' => 'moi',
            ];
        });
    }

    public static function portalData(int $parentUserId): array
    {
        $parent = Store::one("SELECT u.user_id AS userId, u.hoten AS hoTen, u.sdt, u.email, u.diachi AS diaChi
            FROM users u WHERE u.user_id=? AND u.is_deleted=0", [$parentUserId]);

        $students = Store::rows("SELECT hv.id, hv.ma_hv AS maHv, hv.ho_ten AS hoTen,
                hv.ngay_sinh AS ngaySinh, hv.gioi_tinh AS gioiTinh, hv.truong,
                hv.cap_lop_id AS capLopId, cl.ten AS tenCapLop, hv.trang_thai AS trangThai
            FROM hoc_vien hv
            LEFT JOIN cap_lop cl ON cl.id=hv.cap_lop_id
            WHERE hv.phu_huynh_user_id=? AND hv.deleted_at IS NULL
            ORDER BY hv.id ASC", [$parentUserId]);

        $schedules = [];
        $learningNotes = [];
        $invoices = [];

        foreach ($students as $student) {
            $sid = (string)$student['id'];

            // 1. Thời khóa biểu của học viên (không trả về bất kỳ trường đơn giá/lương nào)
            $schedRows = Store::rows("SELECT b.id AS buoiHocId, b.ngay, b.gio_bat_dau AS gioBatDau,
                    b.gio_ket_thuc AS gioKetThuc, b.phong_hoc AS phongHoc, b.trang_thai AS trangThai,
                    b.loai, b.chu_de_bai_hoc AS chuDe, b.noi_dung_bai_hoc AS noiDung,
                    b.file_bai_giang_url AS fileBaiGiangUrl,
                    bt.id AS baiTapId, bt.tieu_de AS baiTap, bt.noi_dung AS ghiChuTaiLieu,
                    bt.file_de_bai_url AS fileUrl, bt.han_nop AS hanNop,
                    cs.ten_co_so AS tenCoSo,
                    l.ten_lop AS tenLop, l.ma_lop AS maLop, l.hinh_thuc AS hinhThuc,
                    COALESCE(k.ten, m.ten, l.ten_lop) AS tenKhoaHoc,
                    u_gv.hoten AS tenNguoiDay
                FROM lop_hoc_vien lhv
                JOIN lop_hoc l ON l.id=lhv.lop_id AND l.deleted_at IS NULL
                LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
                LEFT JOIN mon_hoc m ON m.id=l.mon_hoc_id
                JOIN buoi_hoc b ON b.lop_id=l.id
                LEFT JOIN co_so cs ON cs.id=b.co_so_id
                LEFT JOIN bai_tap bt ON bt.id=(SELECT MAX(hw.id) FROM bai_tap hw WHERE hw.buoi_hoc_id=b.id)
                LEFT JOIN nhan_su_chuyen_mon ns ON ns.id=l.nguoi_day_chinh_id
                LEFT JOIN users u_gv ON u_gv.user_id=COALESCE((SELECT pc.user_id FROM phan_cong_buoi_hoc pc
                    WHERE pc.buoi_hoc_id=b.id AND pc.vai_tro='day_chinh' AND pc.trang_thai<>'da_go' LIMIT 1),ns.user_id)
                WHERE lhv.hoc_vien_id=? AND lhv.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop')
                  AND (b.loai='thuong' OR b.pham_vi_bu='ca_lop'
                       OR EXISTS (SELECT 1 FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=b.id AND hb.hoc_vien_id=lhv.hoc_vien_id))
                ORDER BY b.ngay DESC, b.gio_bat_dau ASC LIMIT 50", [(int)$student['id']]);

            $statusMap = [
                'du_kien' => 'Sắp diễn ra',
                'dang_day' => 'Đang học',
                'da_day' => 'Đã hoàn thành',
                'huy' => 'Đã hủy',
                'doi' => 'Đã dời lịch',
            ];
            $schedules[$sid] = array_map(static function (array $r) use ($statusMap): array {
                $room = $r['hinhThuc'] === 'online'
                    ? 'Online' . ($r['phongHoc'] ? ' (' . $r['phongHoc'] . ')' : '')
                    : 'Offline' . ($r['phongHoc'] ? ' - ' . $r['phongHoc'] : '');
                $material = \App\Modules\Academic\Features\Attendance\SessionMaterialService::describe(
                    ['id' => (int)$r['buoiHocId'], 'file_bai_giang_url' => $r['fileBaiGiangUrl']],
                    $r['baiTapId'] ? ['id' => (int)$r['baiTapId'], 'tieu_de' => $r['baiTap'], 'noi_dung' => $r['ghiChuTaiLieu'], 'file_de_bai_url' => $r['fileUrl']] : null
                );
                return [
                    'buoiHocId' => (int)$r['buoiHocId'],
                    'ngay' => $r['ngay'],
                    'gioBatDau' => $r['gioBatDau'],
                    'maLop' => $r['maLop'],
                    'loai' => $r['loai'],
                    'trangThai' => $r['trangThai'],
                    'chuDe' => $r['chuDe'],
                    'noiDung' => $r['noiDung'],
                    'baiTap' => $r['baiTap'],
                    'fileUrl' => $material['url'] ?: $r['fileUrl'],
                    'fileName' => $material['ten_tep'],
                    'ghiChuTaiLieu' => $r['ghiChuTaiLieu'],
                    'hanNop' => $r['hanNop'] ? date('d/m/Y H:i', strtotime($r['hanNop'])) : null,
                    'date' => date('d/m/Y', strtotime((string)$r['ngay'])),
                    'time' => substr((string)$r['gioBatDau'], 0, 5) . ' - ' . substr((string)$r['gioKetThuc'], 0, 5),
                    'course' => $r['tenKhoaHoc'] ?: $r['tenLop'],
                    'room' => $r['tenCoSo'] ? $r['tenCoSo'].' · '.$room : $room,
                    'teacher' => $r['tenNguoiDay'] ?: 'Giáo viên trung tâm',
                    'status' => $statusMap[$r['trangThai']] ?? $r['trangThai'],
                    'canLeave' => $r['trangThai'] === 'du_kien' && strtotime($r['ngay'].' '.$r['gioBatDau']) > time(),
                ];
            }, $schedRows);

            // 2. Sổ theo dõi buổi học & điểm bài tập
            $noteRows = Store::rows("SELECT s.id, s.buoi_hoc_id AS buoiHocId, b.ngay, COALESCE(k.ten, l.ten_lop) AS tenKhoaHoc,
                    bt.tieu_de AS baiTap, bt.file_de_bai_url AS fileUrl, bt.han_nop AS hanNop,
                    s.trang_thai_diem_danh AS diemDanh, s.diem_bai_tap_giay AS diemBaiTap,
                    s.muc_tiep_thu AS mucTiepThu, s.nhan_xet_buoi_hoc AS nhanXetBuoiHoc,
                    s.nhan_xet_bai_tap AS nhanXetBaiTap
                FROM so_theo_doi_buoi_hoc s
                JOIN buoi_hoc b ON b.id=s.buoi_hoc_id
                JOIN lop_hoc l ON l.id=b.lop_id
                LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
                LEFT JOIN bai_tap bt ON bt.id=COALESCE(s.bai_tap_id,
                    (SELECT MAX(hw.id) FROM bai_tap hw WHERE hw.buoi_hoc_id=b.id))
                WHERE s.hoc_vien_id=?
                ORDER BY b.ngay DESC, s.id DESC LIMIT 50", [(int)$student['id']]);

            $attendMap = [
                'co_mat' => 'Có mặt',
                'di_tre' => 'Đi trễ',
                'vang_co_phep' => 'Vắng có phép',
                'vang_khong_phep' => 'Vắng không phép',
            ];
            $learningNotes[$sid] = array_map(static function (array $n) use ($attendMap): array {
                return [
                    'buoiHocId' => (int)$n['buoiHocId'],
                    'baiTap' => $n['baiTap'],
                    'fileUrl' => $n['fileUrl'],
                    'hanNop' => $n['hanNop'] ? date('d/m/Y H:i', strtotime($n['hanNop'])) : null,
                    'date' => date('d/m/Y', strtotime((string)$n['ngay'])),
                    'course' => $n['tenKhoaHoc'],
                    'attend' => $attendMap[$n['diemDanh']] ?? $n['diemDanh'],
                    'score' => $n['diemBaiTap'] !== null ? ($n['diemBaiTap'] . ' / 10') : '—',
                    'star' => $n['mucTiepThu'] !== null ? ($n['mucTiepThu'] . ' / 5 ⭐') : '—',
                    'comment' => $n['nhanXetBuoiHoc'] ?: ($n['nhanXetBaiTap'] ?: 'Đã ghi nhận chuyên cần.'),
                ];
            }, $noteRows);

            // 3. Hóa đơn học phí trọn khóa của học viên
            $invRows = Store::rows("SELECT h.id, h.ma_hd AS maHd, h.phai_thu AS phaiThu, h.da_thu AS daThu,
                    h.trang_thai AS trangThai, h.han_thanh_toan AS hanThanhToan,
                    COALESCE(k.ten, l.ten_lop, 'Học phí khóa học') AS tenKhoa
                FROM hoa_don h
                LEFT JOIN dang_ky_khoa_hoc dk ON dk.id=h.dang_ky_khoa_hoc_id
                LEFT JOIN khoa_hoc k ON k.id=dk.khoa_hoc_id
                LEFT JOIN lop_hoc_vien lhv ON lhv.id=h.lop_hoc_vien_id
                LEFT JOIN lop_hoc l ON l.id=lhv.lop_id
                WHERE COALESCE(dk.hoc_vien_id, lhv.hoc_vien_id)=?
                  AND h.trang_thai NOT IN ('nhap','huy')
                ORDER BY h.id DESC", [(int)$student['id']]);

            $invoices[$sid] = array_map(static function (array $inv): array {
                return [
                    'id' => (int)$inv['id'],
                    'code' => $inv['maHd'],
                    'course' => $inv['tenKhoa'],
                    'must' => (int)$inv['phaiThu'],
                    'paid' => (int)$inv['daThu'],
                    'deadline' => $inv['hanThanhToan'] ? date('d/m/Y', strtotime((string)$inv['hanThanhToan'])) : '—',
                    'status' => $inv['trangThai'],
                ];
            }, $invRows);
        }

        // 4. Lịch sử đơn xin nghỉ cá nhân của các con thuộc phụ huynh này
        $leaveRows = Store::rows("SELECT dxn.id, dxn.buoi_hoc_id AS buoiHocId, dxn.hoc_vien_id AS hocVienId, dxn.ly_do AS lyDo,
                dxn.ly_do_tu_choi AS feedback,
                dxn.trang_thai AS trangThai, dxn.created_at AS createdAt,
                b.ngay, COALESCE(k.ten, l.ten_lop) AS tenKhoaHoc
            FROM don_xin_nghi dxn
            JOIN hoc_vien hv ON hv.id=dxn.hoc_vien_id AND hv.deleted_at IS NULL
            JOIN buoi_hoc b ON b.id=dxn.buoi_hoc_id
            JOIN lop_hoc l ON l.id=b.lop_id
            LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
            WHERE hv.phu_huynh_user_id=? AND dxn.doi_tuong='hoc_vien'
            ORDER BY dxn.id DESC LIMIT 50", [$parentUserId]);

        $leaveStatusMap = [
            'cho_duyet' => 'Chờ giáo vụ duyệt',
            'da_duyet' => 'Đã duyệt',
            'tu_choi' => 'Từ chối',
            'da_huy' => 'Đã hủy',
        ];
        $leaveRequests = array_map(static function (array $r) use ($leaveStatusMap): array {
            return [
                'id' => (int)$r['id'],
                'code' => 'NP-' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT),
                'studentId' => (string)$r['hocVienId'],
                'buoiHocId' => (int)$r['buoiHocId'],
                'feedback' => $r['feedback'],
                'session' => date('d/m/Y', strtotime((string)$r['ngay'])) . ' · ' . $r['tenKhoaHoc'],
                'reason' => $r['lyDo'],
                'date' => date('d/m/Y', strtotime((string)$r['createdAt'])),
                'status' => $leaveStatusMap[$r['trangThai']] ?? $r['trangThai'],
            ];
        }, $leaveRows);

        // 5. Lịch sử giao dịch trực tuyến
        $txRows = Store::rows("SELECT gd.id, gd.ma_yeu_cau AS maYeuCau, gd.nha_cung_cap AS nhaCungCap,
                gd.ma_giao_dich_cong AS maGiaoDichCong, gd.so_tien AS soTien,
                gd.trang_thai AS trangThai, gd.created_at AS createdAt,
                hd.ma_hd AS maHd, COALESCE(dk.hoc_vien_id,lhv.hoc_vien_id) AS studentId
            FROM giao_dich_truc_tuyen gd
            JOIN hoa_don hd ON hd.id=gd.hoa_don_id
            LEFT JOIN dang_ky_khoa_hoc dk ON dk.id=hd.dang_ky_khoa_hoc_id
            LEFT JOIN lop_hoc_vien lhv ON lhv.id=hd.lop_hoc_vien_id
            WHERE gd.phu_huynh_id=?
            ORDER BY gd.id DESC LIMIT 50", [$parentUserId]);

        $transactions = array_map(static function (array $tx): array {
            return [
                'id' => (int)$tx['id'],
                'studentId' => (string)$tx['studentId'],
                'code' => $tx['maYeuCau'],
                'invCode' => $tx['maHd'],
                'channel' => $tx['nhaCungCap'],
                'amount' => (int)$tx['soTien'],
                'ref' => $tx['maGiaoDichCong'] ?: $tx['maYeuCau'],
                'time' => date('d/m/Y H:i', strtotime((string)$tx['createdAt'])),
                'status' => $tx['trangThai'] === 'thanh_cong' ? 'da_xac_nhan' : $tx['trangThai'],
            ];
        }, $txRows);

        $courses = Store::rows("SELECT k.id, k.ma, k.ten, k.mo_ta AS moTa, k.hoc_phi AS hocPhi
            FROM khoa_hoc k WHERE k.is_active=1 ORDER BY k.id ASC");

        return [
            'serverTime' => date('c'),
            'parent' => $parent,
            'students' => $students,
            'courses' => $courses,
            'schedules' => $schedules,
            'learningNotes' => $learningNotes,
            'invoices' => $invoices,
            'leaveRequests' => $leaveRequests,
            'transactions' => $transactions,
        ];
    }

    public static function createEnrollment(int $parentUserId, array $data): array
    {
        return Store::transaction(function () use ($parentUserId, $data) {
            $course = !empty($data['khoaHocId'])
                ? Store::one('SELECT * FROM khoa_hoc WHERE id=? AND is_active=1 FOR UPDATE', [$data['khoaHocId']])
                : Store::one('SELECT * FROM khoa_hoc WHERE ma=? AND is_active=1 FOR UPDATE', [$data['maKhoa']]);

            if (!$course) {
                throw new \App\Shared\ApiException(404, 'Khóa học không tồn tại hoặc đã ngừng nhận đăng ký.', 'COURSE_NOT_FOUND');
            }

            if (!empty($data['hocVienId'])) {
                $student = Store::one('SELECT id, ma_hv, ho_ten FROM hoc_vien WHERE id=? AND phu_huynh_user_id=? AND deleted_at IS NULL FOR UPDATE', [
                    $data['hocVienId'],
                    $parentUserId,
                ]);
                if (!$student) {
                    throw new \App\Shared\ApiException(403, 'Học viên không thuộc quyền quản lý của tài khoản phụ huynh này.', 'FORBIDDEN_STUDENT');
                }
            } else {
                $capLopId = $data['capLopId'] ?? null;
                if (!$capLopId) {
                    $cap = Store::one('SELECT id FROM cap_lop ORDER BY id ASC LIMIT 1');
                    $capLopId = $cap ? (int)$cap['id'] : 9;
                }
                $maHv = 'HV-' . strtoupper(bin2hex(random_bytes(3)));
                $studentId = Store::insert('hoc_vien', [
                    'phu_huynh_user_id' => $parentUserId,
                    'ma_hv' => $maHv,
                    'ho_ten' => $data['hoTenHocVien'],
                    'cap_lop_id' => $capLopId,
                    'truong' => $data['truong'] ?? null,
                    'trang_thai' => 'dang_hoc',
                ]);
                $student = [
                    'id' => $studentId,
                    'ma_hv' => $maHv,
                    'ho_ten' => $data['hoTenHocVien'],
                ];
            }

            $registration = Store::one(
                "SELECT * FROM dang_ky_khoa_hoc WHERE hoc_vien_id=? AND khoa_hoc_id=? AND trang_thai IN ('cho_xep_lop','dang_hoc') ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [(int)$student['id'], (int)$course['id']]
            );
            if (!$registration) {
                $regId = Store::insert('dang_ky_khoa_hoc', [
                    'hoc_vien_id' => (int)$student['id'],
                    'khoa_hoc_id' => (int)$course['id'],
                    'hoc_phi_snapshot' => $course['hoc_phi'],
                    'trang_thai' => 'cho_xep_lop',
                ]);
                $registration = Store::one('SELECT * FROM dang_ky_khoa_hoc WHERE id=?', [$regId]);
                Store::recordAudit($parentUserId, 'them', 'dang_ky_khoa_hoc', $regId, null, $registration);
            }

            $issuer = Store::one("SELECT a.user_id FROM account a
                JOIN users u ON u.user_id=a.user_id
                JOIN role_group g ON g.group_id=a.group_id
                WHERE g.group_name IN ('Admin','NhanVien') AND a.status='active' AND a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0
                ORDER BY g.group_id ASC, a.account_id ASC LIMIT 1");
            $issuerId = $issuer ? (int)$issuer['user_id'] : $parentUserId;

            $invoice = Store::one('SELECT * FROM hoa_don WHERE dang_ky_khoa_hoc_id=? FOR UPDATE', [(int)$registration['id']]);
            if (!$invoice) {
                $maHd = 'HD-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $invId = Store::insert('hoa_don', [
                    'ma_hd' => $maHd,
                    'dang_ky_khoa_hoc_id' => (int)$registration['id'],
                    'tong_tien' => $registration['hoc_phi_snapshot'],
                    'giam_gia' => 0,
                    'trang_thai' => 'nhap',
                    'han_thanh_toan' => date('Y-m-d', strtotime('+7 days')),
                    'nguoi_lap_id' => $issuerId,
                ]);
                Store::insert('hoa_don_chi_tiet', [
                    'hoa_don_id' => $invId,
                    'loai_khoan' => 'khoa_hoc',
                    'mo_ta' => 'Học phí trọn khóa: ' . $course['ten'],
                    'don_gia' => $registration['hoc_phi_snapshot'],
                ]);
                Store::execute(
                    "UPDATE hoa_don SET trang_thai='chua_thanh_toan', phat_hanh_luc=NOW(), phat_hanh_boi=? WHERE id=?",
                    [$issuerId, $invId]
                );
                $invoice = Store::one('SELECT * FROM hoa_don WHERE id=?', [$invId]);
                Store::recordAudit($issuerId, 'them', 'hoa_don', $invId, null, $invoice);
            } elseif ($invoice['trang_thai'] === 'nhap') {
                Store::execute(
                    "UPDATE hoa_don SET trang_thai='chua_thanh_toan', phat_hanh_luc=NOW(), phat_hanh_boi=? WHERE id=?",
                    [$issuerId, (int)$invoice['id']]
                );
                $invoice = Store::one('SELECT * FROM hoa_don WHERE id=?', [(int)$invoice['id']]);
            }

            $remaining = max(0, (int)$invoice['phai_thu'] - (int)$invoice['da_thu']);

            return [
                'dangKyId' => (int)$registration['id'],
                'hocVienId' => (int)$student['id'],
                'tenHocVien' => $student['ho_ten'],
                'khoaHocId' => (int)$course['id'],
                'maKhoa' => $course['ma'],
                'tenKhoa' => $course['ten'],
                'hoaDon' => [
                    'id' => (int)$invoice['id'],
                    'code' => $invoice['ma_hd'],
                    'course' => $course['ten'],
                    'studentName' => $student['ho_ten'],
                    'must' => (int)$invoice['phai_thu'],
                    'paid' => (int)$invoice['da_thu'],
                    'remaining' => $remaining,
                    'status' => $invoice['trang_thai'],
                ],
            ];
        });
    }

    public static function createLeaveRequest(int $parentUserId, array $data): array
    {
        return Store::transaction(function () use ($parentUserId, $data) {
            $student = Store::one('SELECT id, ho_ten FROM hoc_vien WHERE id=? AND phu_huynh_user_id=? AND deleted_at IS NULL FOR UPDATE', [
                $data['hocVienId'],
                $parentUserId,
            ]);
            if (!$student) {
                throw new \App\Shared\ApiException(403, 'Học viên không thuộc quyền quản lý của tài khoản phụ huynh này.', 'FORBIDDEN_STUDENT');
            }

            $session = Store::one("SELECT b.id, b.ngay, b.gio_bat_dau AS gioBatDau, b.trang_thai, COALESCE(k.ten, l.ten_lop) AS tenKhoaHoc
                FROM buoi_hoc b
                JOIN lop_hoc l ON l.id=b.lop_id
                LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
                JOIN lop_hoc_vien lhv ON lhv.lop_id=l.id
                WHERE b.id=? AND lhv.hoc_vien_id=? AND l.deleted_at IS NULL
                  AND lhv.trang_thai IN ('dang_hoc','hoc_thu')
                  AND (b.loai='thuong' OR b.pham_vi_bu='ca_lop'
                    OR EXISTS (SELECT 1 FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=b.id AND hb.hoc_vien_id=lhv.hoc_vien_id))",
                [$data['buoiHocId'], $data['hocVienId']]);
            if (!$session) {
                throw new \App\Shared\ApiException(404, 'Không tìm thấy buổi học của học viên.', 'SESSION_NOT_FOUND');
            }
            if ($session['trang_thai'] !== 'du_kien' || strtotime($session['ngay'].' '.$session['gioBatDau']) <= time()) {
                throw new \App\Shared\ApiException(409, 'Chỉ có thể gửi đơn xin nghỉ cho buổi học dự kiến sắp diễn ra.');
            }

            $existing = Store::one("SELECT id FROM don_xin_nghi
                WHERE buoi_hoc_id=? AND doi_tuong='hoc_vien' AND hoc_vien_id=? AND trang_thai IN ('cho_duyet','da_duyet')", [
                $data['buoiHocId'],
                $data['hocVienId'],
            ]);
            if ($existing) {
                throw new \App\Shared\ApiException(409, 'Buổi học này đã có đơn xin nghỉ đang chờ duyệt hoặc đã duyệt.');
            }

            $id = Store::insert('don_xin_nghi', [
                'buoi_hoc_id' => $data['buoiHocId'],
                'doi_tuong' => 'hoc_vien',
                'hoc_vien_id' => $data['hocVienId'],
                'nguoi_gui_id' => $parentUserId,
                'ly_do' => $data['lyDo'],
                'trang_thai' => 'cho_duyet',
            ]);

            return [
                'id' => $id,
                'code' => 'NP-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT),
                'studentId' => (string)$data['hocVienId'],
                'session' => date('d/m/Y', strtotime((string)$session['ngay'])) . ' · ' . $session['tenKhoaHoc'],
                'reason' => $data['lyDo'],
                'date' => date('d/m/Y'),
                'status' => 'Chờ giáo vụ duyệt',
            ];
        });
    }

    public static function createOnlinePayment(int $parentUserId, array $data): array
    {
        $result = Store::transaction(function () use ($parentUserId, $data) {
            $invoice = Store::one("SELECT h.id, h.ma_hd, h.phai_thu, h.da_thu, h.trang_thai,
                    hv.phu_huynh_user_id
                FROM hoa_don h
                LEFT JOIN dang_ky_khoa_hoc dk ON dk.id=h.dang_ky_khoa_hoc_id
                LEFT JOIN lop_hoc_vien lhv ON lhv.id=h.lop_hoc_vien_id
                JOIN hoc_vien hv ON hv.id=COALESCE(dk.hoc_vien_id, lhv.hoc_vien_id)
                WHERE h.id=? FOR UPDATE", [$data['hoaDonId']]);

            if (!$invoice) {
                throw new \App\Shared\ApiException(404, 'Không tìm thấy hóa đơn học phí.', 'INVOICE_NOT_FOUND');
            }
            if ((int)$invoice['phu_huynh_user_id'] !== $parentUserId) {
                throw new \App\Shared\ApiException(403, 'Hóa đơn không thuộc học viên của phụ huynh này.', 'FORBIDDEN_INVOICE');
            }
            $remaining = (int)$invoice['phai_thu'] - (int)$invoice['da_thu'];
            if (!in_array($invoice['trang_thai'], ['chua_thanh_toan', 'mot_phan'], true) || $remaining <= 0) {
                throw new \App\Shared\ApiException(409, 'Hóa đơn chưa phát hành hoặc đã được thanh toán đủ.');
            }
            if ((int)$data['soTien'] !== $remaining) {
                throw new \App\Shared\ApiException(400, 'Số tiền thanh toán phải đúng bằng số dư còn phải thu của hóa đơn (' . number_format($remaining) . ' đ).');
            }

            $quote = \App\Modules\Finance\Features\Tuition\TuitionService::verifyPaymentToken($data['paymentToken'], (int)$invoice['id'], $parentUserId, $remaining);

            $pending = Store::one("SELECT id,ma_yeu_cau,nha_cung_cap,ma_giao_dich_cong,so_tien,created_at
                FROM giao_dich_truc_tuyen WHERE hoa_don_id=? AND phu_huynh_id=? AND trang_thai IN ('khoi_tao','cho_xac_nhan')
                ORDER BY id DESC LIMIT 1", [$invoice['id'], $parentUserId]);
            if ($pending) {
                if (in_array($pending['nha_cung_cap'], ['PAYOS', 'SEPAY'], true)) throw new \App\Shared\ApiException(409,'Hóa đơn có yêu cầu thanh toán tự động (' . $pending['nha_cung_cap'] . '). Hãy mở lại mã QR và kiểm tra giao dịch, không gửi thêm thông báo chuyển khoản.');
                return ['id'=>(int)$pending['id'], 'code'=>$pending['ma_yeu_cau'], 'invCode'=>$invoice['ma_hd'],
                    'channel'=>$pending['nha_cung_cap'], 'amount'=>(int)$pending['so_tien'],
                    'ref'=>$pending['ma_giao_dich_cong'], 'time'=>date('d/m/Y H:i', strtotime($pending['created_at'])),
                    'status'=>'cho_xac_nhan'];
            }

            $maYeuCau = 'GD-WEB-' . strtoupper(bin2hex(random_bytes(5)));
            $maGiaoDichCong = 'REF-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $invoice['ma_hd'];
            $provider = strtoupper(trim($data['nhaCungCap']));

            $transferMeta = ['action' => 'bank_transfer_reported', 'receivingAccount' => $quote['bank'], 'memo' => $quote['memo']];
            // Bước 1: Insert với trạng thái khoi_tao theo đúng trigger trg_gd_bi
            $txId = Store::insert('giao_dich_truc_tuyen', [
                'ma_yeu_cau' => $maYeuCau,
                'hoa_don_id' => (int)$invoice['id'],
                'phu_huynh_id' => $parentUserId,
                'nha_cung_cap' => $provider,
                'ma_giao_dich_cong' => $maGiaoDichCong,
                'so_tien' => $remaining,
                'tien_te' => 'VND',
                'trang_thai' => 'khoi_tao',
                'metadata_json' => json_encode(['bank_transfer_reported' => $transferMeta], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

            Store::recordAudit($parentUserId, 'them', 'giao_dich_truc_tuyen', $txId, null, $transferMeta);

            // Bước 2: Chuyển sang cho_xac_nhan và ghi su_kien_thanh_toan để Admin đối soát
            Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan' WHERE id=?", [$txId]);

            $payloadJson = json_encode([
                'maYeuCau' => $maYeuCau,
                'hoaDonId' => (int)$invoice['id'],
                'phuHuynhId' => $parentUserId,
                'soTien' => $remaining,
                'nhaCungCap' => $provider,
                'maGiaoDichCong' => $maGiaoDichCong,
            ], JSON_UNESCAPED_UNICODE);

            Store::insert('su_kien_thanh_toan', [
                'giao_dich_id' => $txId,
                'nha_cung_cap' => $provider,
                'ma_su_kien' => 'EVT-' . $maYeuCau,
                'payload_sha256' => hash('sha256', (string)$payloadJson),
                'trang_thai' => 'cho_xu_ly',
            ]);

            return [
                'id' => $txId,
                'code' => $maYeuCau,
                'invCode' => $invoice['ma_hd'],
                'channel' => $provider,
                'amount' => $remaining,
                'ref' => $maGiaoDichCong,
                'time' => date('d/m/Y H:i'),
                'status' => 'cho_xac_nhan',
            ];
        });

        return $result;
    }
}
