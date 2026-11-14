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
            ORDER BY k.id ASC LIMIT 12");

        $teachers = Store::rows("SELECT ns.id, ns.ma_ns AS maNs, ns.phan_loai AS phanLoai,
                ns.nghe_nghiep AS ngheNghiep, ns.don_vi_cong_tac AS donViCongTac,
                ns.gioi_thieu AS gioiThieu, ns.kinh_nghiem_nam AS kinhNghiemNam,
                ns.hinh_thuc_day AS hinhThucDay, u.hoten AS hoTen,
                (SELECT GROUP_CONCAT(DISTINCT m.ten SEPARATOR ', ')
                 FROM nhan_su_mon nsm JOIN mon_hoc m ON m.id=nsm.mon_hoc_id
                 WHERE nsm.nhan_su_id=ns.id) AS chuyenMon
            FROM nhan_su_chuyen_mon ns
            JOIN users u ON u.user_id=ns.user_id AND u.is_deleted=0
            WHERE ns.deleted_at IS NULL AND ns.trang_thai='hoat_dong'
            ORDER BY ns.kinh_nghiem_nam DESC, ns.id ASC LIMIT 8");

        return [
            'courses' => $courses,
            'teachers' => $teachers,
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
                    l.ten_lop AS tenLop, l.ma_lop AS maLop, l.hinh_thuc AS hinhThuc,
                    COALESCE(k.ten, m.ten, l.ten_lop) AS tenKhoaHoc,
                    u_gv.hoten AS tenNguoiDay
                FROM lop_hoc_vien lhv
                JOIN lop_hoc l ON l.id=lhv.lop_id AND l.deleted_at IS NULL
                LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
                LEFT JOIN mon_hoc m ON m.id=l.mon_hoc_id
                JOIN buoi_hoc b ON b.lop_id=l.id
                LEFT JOIN nhan_su_chuyen_mon ns ON ns.id=l.nguoi_day_chinh_id
                LEFT JOIN users u_gv ON u_gv.user_id=ns.user_id
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
                return [
                    'buoiHocId' => (int)$r['buoiHocId'],
                    'date' => date('d/m/Y', strtotime((string)$r['ngay'])),
                    'time' => substr((string)$r['gioBatDau'], 0, 5) . ' - ' . substr((string)$r['gioKetThuc'], 0, 5),
                    'course' => $r['tenKhoaHoc'] ?: $r['tenLop'],
                    'room' => $room,
                    'teacher' => $r['tenNguoiDay'] ?: 'Giáo viên trung tâm',
                    'status' => $statusMap[$r['trangThai']] ?? $r['trangThai'],
                    'canLeave' => $r['trangThai'] === 'du_kien',
                ];
            }, $schedRows);

            // 2. Sổ theo dõi buổi học & điểm bài tập
            $noteRows = Store::rows("SELECT s.id, b.ngay, COALESCE(k.ten, l.ten_lop) AS tenKhoaHoc,
                    s.trang_thai_diem_danh AS diemDanh, s.diem_bai_tap_giay AS diemBaiTap,
                    s.muc_tiep_thu AS mucTiepThu, s.nhan_xet_buoi_hoc AS nhanXetBuoiHoc,
                    s.nhan_xet_bai_tap AS nhanXetBaiTap
                FROM so_theo_doi_buoi_hoc s
                JOIN buoi_hoc b ON b.id=s.buoi_hoc_id
                JOIN lop_hoc l ON l.id=b.lop_id
                LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
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
        $leaveRows = Store::rows("SELECT dxn.id, dxn.hoc_vien_id AS hocVienId, dxn.ly_do AS lyDo,
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
                hd.ma_hd AS maHd
            FROM giao_dich_truc_tuyen gd
            JOIN hoa_don hd ON hd.id=gd.hoa_don_id
            WHERE gd.phu_huynh_id=?
            ORDER BY gd.id DESC LIMIT 50", [$parentUserId]);

        $transactions = array_map(static function (array $tx): array {
            return [
                'id' => (int)$tx['id'],
                'code' => $tx['maYeuCau'],
                'invCode' => $tx['maHd'],
                'channel' => $tx['nhaCungCap'],
                'amount' => (int)$tx['soTien'],
                'ref' => $tx['maGiaoDichCong'] ?: $tx['maYeuCau'],
                'time' => date('d/m/Y H:i', strtotime((string)$tx['createdAt'])),
                'status' => $tx['trangThai'] === 'thanh_cong' ? 'da_xac_nhan' : $tx['trangThai'],
            ];
        }, $txRows);

        return [
            'parent' => $parent,
            'students' => $students,
            'schedules' => $schedules,
            'learningNotes' => $learningNotes,
            'invoices' => $invoices,
            'leaveRequests' => $leaveRequests,
            'transactions' => $transactions,
        ];
    }

    public static function createLeaveRequest(int $parentUserId, array $data): array
    {
        return Store::transaction(function () use ($parentUserId, $data) {
            $student = Store::one('SELECT id, ho_ten FROM hoc_vien WHERE id=? AND phu_huynh_user_id=? AND deleted_at IS NULL', [
                $data['hocVienId'],
                $parentUserId,
            ]);
            if (!$student) {
                throw new \App\Shared\ApiException(403, 'Học viên không thuộc quyền quản lý của tài khoản phụ huynh này.', 'FORBIDDEN_STUDENT');
            }

            $session = Store::one("SELECT b.id, b.ngay, b.trang_thai, COALESCE(k.ten, l.ten_lop) AS tenKhoaHoc
                FROM buoi_hoc b
                JOIN lop_hoc l ON l.id=b.lop_id
                LEFT JOIN khoa_hoc k ON k.id=l.khoa_hoc_id
                JOIN lop_hoc_vien lhv ON lhv.lop_id=l.id
                WHERE b.id=? AND lhv.hoc_vien_id=?", [$data['buoiHocId'], $data['hocVienId']]);
            if (!$session) {
                throw new \App\Shared\ApiException(404, 'Không tìm thấy buổi học của học viên.', 'SESSION_NOT_FOUND');
            }
            if ($session['trang_thai'] !== 'du_kien') {
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
        return Store::transaction(function () use ($parentUserId, $data) {
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

            $maYeuCau = 'GD-WEB-' . strtoupper(bin2hex(random_bytes(5)));
            $maGiaoDichCong = 'REF-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $invoice['ma_hd'];
            $provider = strtoupper(trim($data['nhaCungCap']));

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
            ]);

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
    }
}
