<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\{Access, ApiException};

final class ReportsService
{
    private const ATT_LABELS = [
        'co_mat' => 'Có mặt',
        'di_tre' => 'Đi trễ',
        'vang_co_phep' => 'Vắng có phép',
        'vang_khong_phep' => 'Vắng không phép',
        'chua_diem_danh' => 'Chưa điểm danh',
        'chua_dien_ra' => 'Chưa diễn ra',
    ];

    private const CLASS_STATUS_LABELS = [
        'sap_bat_dau' => 'Sắp bắt đầu',
        'dang_hoc' => 'Đang học',
        'tam_dung' => 'Tạm dừng',
        'hoan_thanh' => 'Hoàn thành',
        'huy' => 'Đã hủy',
    ];

    private const SESSION_STATUS_LABELS = [
        'du_kien' => 'Sắp diễn ra',
        'dang_day' => 'Đang dạy',
        'da_day' => 'Đã dạy',
        'huy' => 'Đã hủy',
        'doi' => 'Đã đổi lịch',
    ];

    private const PAY_METHOD_LABELS = [
        'tien_mat' => 'Tiền mặt',
        'chuyen_khoan' => 'Chuyển khoản',
        'vi_dien_tu' => 'Ví điện tử',
        'the' => 'Thẻ',
    ];

    private const ROLE_GROUP_LABELS = [
        'giao_vien' => 'Giáo viên',
        'gia_su' => 'Gia sư',
        'tro_giang' => 'Trợ giảng',
    ];

    private const PAYROLL_STATUS_LABELS = [
        'mo' => 'Mở (chưa chốt)',
        'da_chot' => 'Đã chốt',
        'da_tra' => 'Đã trả',
        'hon_hop' => 'Nhiều trạng thái',
    ];

    private const DEBT_AGING_LABELS = [
        'qua_han' => 'Quá hạn',
        'chua_den_han' => 'Chưa đến hạn',
        'khong_co_han' => 'Chưa đặt hạn',
    ];

    private const INV_STATUS_LABELS = [
        'chua_thanh_toan' => 'Chưa thanh toán',
        'mot_phan' => 'Thanh toán một phần',
        'da_thanh_toan' => 'Đã thanh toán đủ',
    ];

    private const ADM_STATUS_LABELS = [
        'moi' => 'Mới tiếp nhận',
        'dang_tim' => 'Đang tìm người dạy',
        'da_de_xuat' => 'Đã đề xuất',
        'da_chot' => 'Đã chốt',
        'huy' => 'Đã hủy',
    ];

    private const ADM_SOURCE_LABELS = [
        'website' => 'Website',
        'dien_thoai' => 'Điện thoại',
        'truc_tiep' => 'Trực tiếp',
        'gioi_thieu' => 'Giới thiệu',
    ];

    public static function authorize(string $type, string $action = 'view'): array
    {
        $user = Access::user();
        if (!in_array($user['role'], ['ADMIN', 'GIAO_VU'], true)) {
            Access::forbidden();
        }
        // Financial revenue, payroll, and debt reports are strictly Admin-only per EduCenter business policy.
        if (in_array($type, ReportsValidator::ADMIN_ONLY_TYPES, true) && $user['role'] !== 'ADMIN') {
            $label = match ($type) {
                'payroll' => 'báo cáo công dạy và quỹ lương',
                'debt' => 'báo cáo công nợ học phí',
                default => 'báo cáo doanh thu',
            };
            throw new ApiException(403, "Chỉ Quản trị viên (Admin) được xem và xuất $label.", 'FORBIDDEN');
        }
        if ($user['role'] === 'GIAO_VU') {
            $allowed = false;
            foreach ($user['permissions'] as $perm) {
                if ($perm['functionName'] === 'BaoCaoThongKe' && !empty($perm[$action])) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) Access::forbidden();
        }
        return $user;
    }

    public static function meta(): array
    {
        $actor = self::authorize('attendance', 'view');
        return ReportsRepository::meta($actor);
    }

    public static function get(string $rawType, array $query): array
    {
        $type = ReportsValidator::type($rawType);
        $actor = self::authorize($type, 'view');
        $filters = ReportsValidator::filters($query, $type, false);

        return match ($type) {
            'attendance' => ReportsRepository::attendance($filters, $actor),
            'revenue' => ReportsRepository::revenue($filters, $actor),
            'class-performance' => ReportsRepository::classPerformance($filters, $actor),
            'payroll' => ReportsRepository::payroll($filters, $actor),
            'debt' => ReportsRepository::debt($filters, $actor),
            'admissions' => ReportsRepository::admissions($filters, $actor),
        };
    }

    private static function fmtDate(?string $ymd): string
    {
        if (!$ymd || strlen($ymd) < 10) return '—';
        [$y, $m, $d] = explode('-', substr($ymd, 0, 10));
        return "$d/$m/$y";
    }

    private static function fmtRate(?float $rate): string
    {
        return $rate === null ? '—' : number_format($rate, 1, '.', '').'%';
    }

    public static function export(string $rawType, array $query): array
    {
        $type = ReportsValidator::type($rawType);
        $actor = self::authorize($type, 'download');
        $filters = ReportsValidator::filters($query, $type, true);
        $format = $filters['format'];

        $data = match ($type) {
            'attendance' => ReportsRepository::attendance($filters, $actor),
            'revenue' => ReportsRepository::revenue($filters, $actor),
            'class-performance' => ReportsRepository::classPerformance($filters, $actor),
            'payroll' => ReportsRepository::payroll($filters, $actor),
            'debt' => ReportsRepository::debt($filters, $actor),
            'admissions' => ReportsRepository::admissions($filters, $actor),
        };

        $exportedAt = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh')))->format('d/m/Y H:i');
        $rangeLabel = $type === 'debt'
            ? 'Toàn bộ hóa đơn còn nợ tại thời điểm xuất'
            : self::fmtDate($filters['tuNgay']).' – '.self::fmtDate($filters['denNgay']);
        $metaRows = [
            ['Phạm vi thời gian', $rangeLabel],
            ['Thời điểm xuất', $exportedAt.' (Asia/Ho_Chi_Minh)'],
            ['Người xuất', $actor['hoTen'].' ('.$actor['role'].')'],
        ];

        if ($type === 'attendance') {
            $title = 'BÁO CÁO CHUYÊN CẦN HỌC VIÊN';
            $slug = 'chuyen-can';
            $sumHeaders = ['Mã lớp', 'Tên lớp', 'Khóa học', 'Cơ sở', 'Số buổi', 'Tổng lượt', 'Đã điểm danh', 'Có mặt', 'Đi trễ', 'Vắng có phép', 'Vắng không phép', 'Chưa điểm danh', 'Chưa diễn ra', 'Tỷ lệ chuyên cần (%)'];
            $sumRows = array_map(static fn(array $r) => [
                $r['maLop'], $r['tenLop'], $r['tenKhoa'], $r['tenCoSo'],
                $r['soBuoi'], $r['tongLuot'], $r['daDiemDanh'], $r['coMat'], $r['diTre'],
                $r['vangCoPhep'], $r['vangKhongPhep'], $r['chuaDiemDanh'], $r['chuaDienRa'],
                self::fmtRate($r['tyLeChuyenCan']),
            ], $data['byClass']);
            $detHeaders = ['Ngày', 'Giờ học', 'Mã lớp', 'Tên lớp', 'Khóa học', 'Cơ sở', 'Mã học viên', 'Họ tên học viên', 'Trạng thái buổi', 'Điểm danh', 'Điểm bài tập', 'Nhận xét'];
            $detRows = array_map(static fn(array $r) => [
                self::fmtDate($r['ngay']),
                substr((string)$r['gioBatDau'], 0, 5).'–'.substr((string)$r['gioKetThuc'], 0, 5),
                $r['maLop'], $r['tenLop'], $r['tenKhoa'], $r['tenCoSo'], $r['maHv'], $r['hoTen'],
                self::SESSION_STATUS_LABELS[$r['trangThaiBuoi']] ?? $r['trangThaiBuoi'],
                self::ATT_LABELS[$r['trangThaiDiemDanh']] ?? $r['trangThaiDiemDanh'],
                $r['diemBaiTap'] !== null ? (float)$r['diemBaiTap'] : '',
                $r['nhanXet'] ?? '',
            ], $data['details']);
            $summarySheet = 'Tong hop chuyen can';
            $detailSheet = 'Chi tiet diem danh';
        } elseif ($type === 'revenue') {
            $title = 'BÁO CÁO DOANH THU THEO KHÓA HỌC VÀ CƠ SỞ';
            $slug = 'doanh-thu';
            $sumHeaders = ['Mã khóa', 'Khóa học', 'Cơ sở', 'Số hóa đơn phát hành', 'Phải thu (VND)', 'Còn nợ trên HĐ trong kỳ (VND)', 'Số phiếu xác nhận', 'Tổng thu (VND)', 'Tổng hoàn (VND)', 'Thực thu ròng (VND)'];
            $sumRows = array_map(static fn(array $r) => [
                $r['maKhoa'], $r['tenKhoa'], $r['tenCoSo'],
                $r['soHoaDon'], $r['phaiThu'], $r['conNo'],
                $r['soPhieu'], $r['tongThu'], $r['tongHoan'], $r['thucThuRong'],
            ], $data['byGroup']);
            $detHeaders = ['Mã phiếu', 'Ngày thanh toán', 'Mã hóa đơn', 'Học viên', 'Khóa học', 'Cơ sở', 'Lớp', 'Loại phiếu', 'Phương thức', 'Số tiền (VND)', 'Mã giao dịch', 'Người thu'];
            $detRows = array_map(static fn(array $r) => [
                'PT-'.$r['phieuId'],
                self::fmtDate($r['ngayThanhToan']),
                $r['maHd'], $r['tenHocVien'], $r['tenKhoa'], $r['tenCoSo'], $r['maLop'],
                $r['loai'] === 'thu' ? 'Phiếu thu' : 'Phiếu hoàn',
                self::PAY_METHOD_LABELS[$r['phuongThuc']] ?? $r['phuongThuc'],
                $r['loai'] === 'thu' ? (int)$r['soTien'] : -(int)$r['soTien'],
                $r['maGiaoDich'] ?? '',
                $r['nguoiThu'],
            ], $data['details']);
            $summarySheet = 'Tong hop doanh thu';
            $detailSheet = 'Chi tiet phieu thu';
        } elseif ($type === 'class-performance') {
            $title = 'BÁO CÁO HIỆU SUẤT VẬN HÀNH LỚP HỌC';
            $slug = 'hieu-suat-lop';
            $sumHeaders = ['Mã lớp', 'Tên lớp', 'Loại lớp', 'Khóa học', 'Cơ sở', 'Môn · Khối', 'Người dạy chính', 'Trạng thái', 'Sĩ số đang học', 'Sĩ số tối đa', 'Lấp đầy (%)', 'Tổng buổi', 'Đã diễn ra', 'Bị hủy', 'Đổi lịch', 'Học bù', 'Có sổ học vụ', 'Tỷ lệ chuyên cần (%)'];
            $sumRows = array_map(static fn(array $r) => [
                $r['maLop'], $r['tenLop'], $r['loaiLop'], $r['tenKhoa'], $r['tenCoSo'],
                $r['tenMon'].' · '.$r['tenCapLop'], $r['nguoiDayChinh'],
                self::CLASS_STATUS_LABELS[$r['trangThai']] ?? $r['trangThai'],
                $r['siSoDangHoc'], $r['siSoToiDa'], self::fmtRate($r['tyLeLapDay']),
                $r['tongBuoiTrongKy'], $r['soBuoiDaDienRa'], $r['soBuoiBiHuy'], $r['soBuoiDoiLich'],
                $r['soBuoiHocBu'], $r['soBuoiCoSoHocVu'], self::fmtRate($r['tyLeChuyenCan']),
            ], $data['classes']);
            $detHeaders = ['Ngày', 'Giờ học', 'Mã lớp', 'Tên lớp', 'Cơ sở', 'Loại buổi', 'Trạng thái buổi', 'Lượt điểm danh', 'Có mặt / Đi trễ'];
            $detRows = array_map(static fn(array $r) => [
                self::fmtDate($r['ngay']),
                substr((string)$r['gioBatDau'], 0, 5).'–'.substr((string)$r['gioKetThuc'], 0, 5),
                $r['maLop'], $r['tenLop'], $r['tenCoSo'],
                $r['loaiBuoi'] === 'bu' ? 'Học bù' : 'Buổi thường',
                self::SESSION_STATUS_LABELS[$r['trangThaiBuoi']] ?? $r['trangThaiBuoi'],
                (int)$r['soLuotDiemDanh'],
                (int)$r['soCoMatHoacTre'],
            ], $data['details']);
            $summarySheet = 'Hieu suat lop';
            $detailSheet = 'Chi tiet buoi hoc';
        } elseif ($type === 'payroll') {
            $title = 'BÁO CÁO CÔNG DẠY VÀ QUỸ LƯƠNG GIẢNG DẠY (ADMIN)';
            $slug = 'cong-day-quy-luong';
            $metaRows[] = ['Đối chiếu Thực thu – Quỹ lương', number_format($data['summary']['thucThuRong']).' − '.number_format($data['summary']['tongTienCongCa']).' = '.number_format($data['summary']['chenhLechThuLuong']).' VND (chỉ đối chiếu thu học phí với tiền công ca)'];
            $sumHeaders = ['Mã NS', 'Họ tên', 'Nhóm nhân sự', 'Số ca hợp lệ', 'Tổng phút công', 'Tổng giờ công', 'Số lần trễ', 'Số phút trễ', 'Tiền công mở (VND)', 'Tiền công đã chốt (VND)', 'Tiền công đã trả (VND)', 'Tổng tiền công (VND)', 'Trạng thái kỳ công'];
            $sumRows = array_map(static fn(array $r) => [
                $r['maNs'], $r['hoTen'], self::ROLE_GROUP_LABELS[$r['nhomNhanSu']] ?? $r['nhomNhanSu'],
                $r['soCa'], $r['tongPhut'], $r['tongGio'], $r['soLanTre'], $r['soPhutTre'],
                $r['tienMo'], $r['tienDaChot'], $r['tienDaTra'], $r['tienCongCa'],
                self::PAYROLL_STATUS_LABELS[$r['trangThaiKy']] ?? $r['trangThaiKy'],
            ], $data['byPerson']);
            $detHeaders = ['Mã PC', 'Ngày', 'Giờ lịch', 'Mã lớp', 'Cơ sở', 'Nhân sự', 'Nhóm', 'Phút tính công', 'Phút đi trễ', 'Cách tính', 'Đơn giá snapshot (VND)', 'Tiền công ca (VND)', 'Trạng thái kỳ'];
            $detRows = array_map(static fn(array $r) => [
                'PC-'.$r['phanCongId'],
                self::fmtDate($r['ngay']),
                substr((string)$r['gioBatDau'], 0, 5).'–'.substr((string)$r['gioKetThuc'], 0, 5),
                $r['maLop'], $r['tenCoSo'], $r['hoTen'],
                self::ROLE_GROUP_LABELS[$r['nhomNhanSu']] ?? $r['nhomNhanSu'],
                (int)$r['soPhutTinhCong'], (int)$r['soPhutTre'],
                $r['cachTinhLuong'] === 'theo_gio' ? 'Theo giờ' : 'Theo buổi',
                (int)$r['donGia'], (int)$r['tienCong'],
                self::PAYROLL_STATUS_LABELS[$r['trangThaiKy']] ?? $r['trangThaiKy'],
            ], $data['details']);
            $summarySheet = 'Tong hop cong luong';
            $detailSheet = 'Chi tiet ca day';
        } elseif ($type === 'debt') {
            $title = 'BÁO CÁO CÔNG NỢ HỌC PHÍ TỒN VÀ QUÁ HẠN';
            $slug = 'cong-no-ton';
            $sumHeaders = ['Mã khóa', 'Khóa học', 'Cơ sở', 'Số HĐ còn nợ', 'Tổng phải thu (VND)', 'Đã thu (VND)', 'Tổng còn nợ (VND)', 'HĐ quá hạn', 'Tiền quá hạn (VND)', 'HĐ chưa đến hạn', 'Tiền chưa đến hạn (VND)'];
            $sumRows = array_map(static fn(array $r) => [
                $r['maKhoa'], $r['tenKhoa'], $r['tenCoSo'], $r['soHoaDonNo'],
                $r['tongPhaiThu'], $r['tongDaThu'], $r['tongConNo'],
                $r['soQuaHan'], $r['tienQuaHan'], $r['soChuaDenHan'], $r['tienChuaDenHan'],
            ], $data['byGroup']);
            $detHeaders = ['Mã HĐ', 'Ngày phát hành', 'Hạn thanh toán', 'Nhóm hạn', 'Số ngày quá hạn', 'Trạng thái HĐ', 'Học viên', 'Phụ huynh', 'SĐT Phụ huynh', 'Khóa học', 'Cơ sở', 'Lớp', 'Phải thu (VND)', 'Đã thu (VND)', 'Còn nợ (VND)'];
            $detRows = array_map(static fn(array $r) => [
                $r['maHd'], self::fmtDate($r['ngayPhatHanh']), self::fmtDate($r['hanThanhToan']),
                self::DEBT_AGING_LABELS[$r['nhomHan']] ?? $r['nhomHan'],
                (int)$r['soNgayQuaHan'],
                self::INV_STATUS_LABELS[$r['trangThai']] ?? $r['trangThai'],
                $r['tenHocVien'], $r['tenPhuHuynh'], $r['sdtPhuHuynh'],
                $r['tenKhoa'], $r['tenCoSo'], $r['maLop'],
                (int)$r['phaiThu'], (int)$r['daThu'], (int)$r['conNo'],
            ], $data['details']);
            $summarySheet = 'Tong hop cong no';
            $detailSheet = 'Chi tiet hoa don no';
        } else {
            $title = 'THỐNG KÊ YÊU CẦU HỌC VÀ TUYỂN SINH';
            $slug = 'tuyen-sinh';
            $sumHeaders = ['Cơ sở mong muốn', 'Môn học', 'Tổng yêu cầu', 'Mới', 'Đang tìm', 'Đã đề xuất', 'Đã chốt', 'Đã hủy', 'Đã mở lớp', 'Tỷ lệ chốt (%)'];
            $sumRows = array_map(static fn(array $r) => [
                $r['tenCoSo'], $r['tenMon'], $r['tongYeuCau'], $r['moi'], $r['dangTim'],
                $r['daDeXuat'], $r['daChot'], $r['huy'], $r['daMoLop'], self::fmtRate($r['tyLeChot']),
            ], $data['byGroup']);
            $detHeaders = ['Mã YC', 'Ngày tạo', 'Khách hàng / Học viên', 'SĐT', 'Môn · Cấp lớp', 'Loại lớp', 'Cơ sở', 'Nguồn', 'Trạng thái hiện tại', 'Số đề xuất', 'Đã mở lớp'];
            $detRows = array_map(static fn(array $r) => [
                $r['maYc'], self::fmtDate($r['createdAt']), $r['khachHang'], $r['sdt'],
                $r['tenMon'].' · '.$r['tenCapLop'], $r['loaiLop'], $r['tenCoSo'],
                self::ADM_SOURCE_LABELS[$r['nguon']] ?? $r['nguon'],
                self::ADM_STATUS_LABELS[$r['trangThai']] ?? $r['trangThai'],
                (int)$r['soDeXuat'], !empty($r['daMoLop']) ? 'Có' : 'Chưa',
            ], $data['details']);
            $summarySheet = 'Tong hop tuyen sinh';
            $detailSheet = 'Chi tiet yeu cau hoc';
        }

        $filename = sprintf('bao-cao-%s-%s-den-%s.%s', $slug, $filters['tuNgay'], $filters['denNgay'], $format);

        if ($format === 'csv') {
            $body = SpreadsheetExport::toCsv($title, $metaRows, $sumHeaders, $sumRows, ['CHI TIẾT'], $detHeaders, $detRows);
            return [
                'filename' => $filename,
                'contentType' => 'text/csv; charset=UTF-8',
                'body' => $body,
            ];
        }

        $body = SpreadsheetExport::toXlsx($title, $metaRows, $summarySheet, $sumHeaders, $sumRows, $detailSheet, $detHeaders, $detRows);
        return [
            'filename' => $filename,
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'body' => $body,
        ];
    }
}
