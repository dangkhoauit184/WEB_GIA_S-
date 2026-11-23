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

    public static function authorize(string $type, string $action = 'view'): array
    {
        $user = Access::user();
        if (!in_array($user['role'], ['ADMIN', 'GIAO_VU'], true)) {
            Access::forbidden();
        }
        // Financial revenue reports are strictly Admin-only per EduCenter business policy.
        if ($type === 'revenue' && $user['role'] !== 'ADMIN') {
            throw new ApiException(403, 'Chỉ Quản trị viên (Admin) được xem và xuất báo cáo doanh thu.', 'FORBIDDEN');
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
        };

        $exportedAt = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh')))->format('d/m/Y H:i');
        $metaRows = [
            ['Khoảng ngày', self::fmtDate($filters['tuNgay']).' – '.self::fmtDate($filters['denNgay'])],
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
            $sumHeaders = ['Mã khóa', 'Khóa học', 'Cơ sở', 'Số hóa đơn phát hành', 'Phải thu (VND)', 'Còn nợ (VND)', 'Số phiếu xác nhận', 'Tổng thu (VND)', 'Tổng hoàn (VND)', 'Thực thu ròng (VND)'];
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
        } else {
            $title = 'BÁO CÁO HIỆU SUẤT LỚP HỌC';
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
