<?php
namespace App\Modules\Communication\Features\Notifications;

use App\Shared\Store;

final class EmailTemplate
{
    public const ALLOWED_TYPES = [
        'hoa_don',
        'phan_cong',
        'go_phan_cong',
        'lop_moi',
        'doi_lich',
        'nhac_checkin',
        'duyet_don',
        'de_xuat',
        'nhan_xet',
        'he_thong',
    ];

    private const TYPE_LABELS = [
        'hoa_don' => 'Học phí & Thanh toán',
        'phan_cong' => 'Phân công ca học',
        'go_phan_cong' => 'Cập nhật phân công ca',
        'lop_moi' => 'Xếp lớp học mới',
        'doi_lich' => 'Cập nhật lịch học',
        'nhac_checkin' => 'Nhắc lịch học sắp diễn ra',
        'duyet_don' => 'Kết quả duyệt yêu cầu',
        'de_xuat' => 'Đề xuất & Tuyển dụng ca',
        'nhan_xet' => 'Sổ buổi học & Nhận xét',
        'he_thong' => 'Thông báo hệ thống',
    ];

    private static function esc(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function formatDate(?string $ymd): string
    {
        if (!$ymd || strlen($ymd) < 10) return '—';
        [$y, $m, $d] = explode('-', substr($ymd, 0, 10));
        return "$d/$m/$y";
    }

    private static function formatTime(?string $hms): string
    {
        return $hms ? substr($hms, 0, 5) : '—';
    }

    private static function formatMoney(mixed $amount): string
    {
        return number_format((float)$amount, 0, ',', '.').' VND';
    }

    private static function baseUrl(): string
    {
        $base = trim((string)(getenv('APP_BASE_URL') ?: 'http://localhost:5000'));
        return rtrim($base !== '' ? $base : 'http://localhost:5000', '/');
    }

    public static function portalUrl(string $role, string $type): string
    {
        $base = self::baseUrl();
        if ($role === 'GIAO_VIEN') {
            $tab = match ($type) {
                'duyet_don' => 'requests',
                'de_xuat' => 'recruit',
                default => 'schedule',
            };
            return "$base/app#/teacher/teach-$tab";
        }
        if ($role === 'GIA_SU') {
            $tab = match ($type) {
                'duyet_don' => 'requests',
                'de_xuat' => 'recruit',
                default => 'schedule',
            };
            return "$base/app#/tutor/tut-$tab";
        }
        if ($role === 'TRO_GIANG') {
            $tab = match ($type) {
                'duyet_don' => 'requests',
                'de_xuat' => 'recruit',
                default => 'schedule',
            };
            return "$base/app#/assistant/ta-$tab";
        }
        return "$base/app";
    }

    /**
     * Build Vietnamese HTML + plain-text email payload.
     * Throws \DomainException on permanent policy/ownership violation (never retried).
     */
    public static function build(array $notification, array $recipient): array
    {
        $type = (string)($notification['loai'] ?? '');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \DomainException('Loại thông báo không nằm trong danh mục gửi email tự động.');
        }

        $title = trim((string)($notification['tieu_de'] ?? 'Thông báo từ EduCenter'));
        $body = trim((string)($notification['noi_dung'] ?? ''));

        // Guard against accidentally mailing OTP or secret credentials
        if (preg_match('/\b(otp|mật khẩu|mat khau|password|token)\b/iu', $title.' '.$body)) {
            throw new \DomainException('Nội dung nhạy cảm không được gửi qua hàng đợi thông báo.');
        }

        $role = (string)($recipient['role'] ?? 'PHU_HUYNH');
        $userId = (int)$recipient['user_id'];
        $details = self::loadSafeDetails($notification, $userId, $role);

        $typeLabel = self::TYPE_LABELS[$type] ?? 'Thông báo nghiệp vụ';
        $subject = '[EduCenter] '.$title;
        $actionUrl = self::portalUrl($role, $type);
        $actionLabel = match ($type) {
            'hoa_don' => 'Xem học phí & thanh toán',
            'nhac_checkin', 'phan_cong', 'go_phan_cong', 'doi_lich' => 'Mở thời khóa biểu',
            'duyet_don' => 'Xem chi tiết đơn từ',
            'de_xuat' => 'Xem đề xuất tuyển lớp',
            'lop_moi' => 'Xem lớp học của bạn',
            default => 'Mở EduCenter ERP',
        };

        $detailRowsHtml = '';
        $detailRowsText = [];
        foreach ($details as [$label, $value]) {
            if ($value === null || $value === '') continue;
            $detailRowsHtml .= '<tr>'
                .'<td style="padding:8px 12px;color:#475569;font-size:13px;border-bottom:1px solid #e2e8f0;width:36%;">'.self::esc($label).'</td>'
                .'<td style="padding:8px 12px;color:#0f172a;font-size:13px;font-weight:600;border-bottom:1px solid #e2e8f0;">'.self::esc($value).'</td>'
                .'</tr>';
            $detailRowsText[] = "- $label: $value";
        }

        $recipientName = trim((string)($recipient['hoten'] ?? 'Quý người dùng'));
        $sentAt = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh')))->format('H:i d/m/Y');

        $html = '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><title>'.self::esc($subject).'</title></head>'
            .'<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Inter,Segoe UI,Arial,sans-serif;color:#0f172a;">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">'
            .'<tr><td style="background:#0d9488;padding:20px 24px;color:#ffffff;">'
            .'<div style="font-size:12px;letter-spacing:0.06em;text-transform:uppercase;opacity:0.9;">EduCenter ERP · '.self::esc($typeLabel).'</div>'
            .'<h1 style="margin:6px 0 0;font-size:19px;line-height:1.35;font-weight:700;color:#ffffff;">'.self::esc($title).'</h1>'
            .'</td></tr>'
            .'<tr><td style="padding:24px;">'
            .'<p style="margin:0 0 12px;font-size:14px;line-height:1.6;">Xin chào <strong>'.self::esc($recipientName).'</strong>,</p>'
            .($body !== '' ? '<p style="margin:0 0 18px;font-size:14px;line-height:1.65;color:#1e293b;">'.nl2br(self::esc($body)).'</p>' : '')
            .($detailRowsHtml !== '' ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;border:1px solid #e2e8f0;border-radius:8px;border-collapse:collapse;background:#f8fafc;">'.$detailRowsHtml.'</table>' : '')
            .'<p style="margin:0 0 20px;"><a href="'.self::esc($actionUrl).'" style="display:inline-block;padding:11px 20px;background:#0d9488;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;">'.self::esc($actionLabel).'</a></p>'
            .'<p style="margin:0;font-size:12px;color:#64748b;line-height:1.5;">Nếu nút trên không hoạt động, vui lòng truy cập: <a href="'.self::esc($actionUrl).'" style="color:#0f766e;">'.self::esc($actionUrl).'</a></p>'
            .'</td></tr>'
            .'<tr><td style="padding:14px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11.5px;color:#64748b;">'
            .'Email thông báo tự động từ hệ thống EduCenter ERP (múi giờ Asia/Ho_Chi_Minh · '.$sentAt.'). Vui lòng không chia sẻ thông tin tài khoản qua email.'
            .'</td></tr></table></body></html>';

        $textLines = [
            "EduCenter ERP - $typeLabel",
            "Tiêu đề: $title",
            "",
            "Xin chào $recipientName,",
        ];
        if ($body !== '') $textLines[] = $body;
        if ($detailRowsText) {
            $textLines[] = "";
            $textLines[] = "Thông tin chi tiết:";
            array_push($textLines, ...$detailRowsText);
        }
        $textLines[] = "";
        $textLines[] = "$actionLabel: $actionUrl";
        $textLines[] = "Thời điểm gửi: $sentAt (Asia/Ho_Chi_Minh)";

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => implode("\n", $textLines),
            'actionUrl' => $actionUrl,
        ];
    }

    private static function loadSafeDetails(array $notification, int $userId, string $role): array
    {
        $objType = (string)($notification['doi_tuong_loai'] ?? '');
        $objId = isset($notification['doi_tuong_id']) ? (int)$notification['doi_tuong_id'] : 0;
        if ($objId <= 0 || $objType === '') return [];

        if ($objType === 'buoi_hoc') {
            $row = Store::one("SELECT b.ngay, b.gio_bat_dau, b.gio_ket_thuc, b.trang_thai, b.loai,
                    COALESCE(b.phong_hoc, l.phong_hoc) AS phong_hoc, l.ma_lop, l.ten_lop, l.hinh_thuc,
                    m.ten AS ten_mon, cl.ten AS ten_cap_lop, cs.ten_co_so
                FROM buoi_hoc b
                JOIN lop_hoc l ON l.id=b.lop_id
                JOIN mon_hoc m ON m.id=l.mon_hoc_id
                JOIN cap_lop cl ON cl.id=l.cap_lop_id
                LEFT JOIN co_so cs ON cs.id=COALESCE(b.co_so_id, l.co_so_id)
                WHERE b.id=? AND l.deleted_at IS NULL", [$objId]);
            if (!$row) return [];
            $place = $row['hinh_thuc'] === 'online'
                ? 'Trực tuyến (Online)'
                : trim(($row['ten_co_so'] ?: 'Tại trung tâm').($row['phong_hoc'] ? ' · '.$row['phong_hoc'] : ''));
            $statusMap = ['du_kien' => 'Sắp diễn ra', 'dang_day' => 'Đang dạy', 'da_day' => 'Đã dạy', 'huy' => 'Đã hủy', 'doi' => 'Đã đổi lịch'];
            return [
                ['Lớp học', $row['ma_lop'].' — '.$row['ten_lop']],
                ['Môn · Khối', $row['ten_mon'].' · '.$row['ten_cap_lop']],
                ['Thời gian', self::formatDate($row['ngay']).' · '.self::formatTime($row['gio_bat_dau']).'–'.self::formatTime($row['gio_ket_thuc'])],
                ['Địa điểm', $place],
                ['Trạng thái buổi', $statusMap[$row['trang_thai']] ?? $row['trang_thai']],
            ];
        }

        if ($objType === 'lop_hoc') {
            $row = Store::one("SELECT l.ma_lop, l.ten_lop, l.hinh_thuc, l.ngay_bat_dau, m.ten AS ten_mon, cl.ten AS ten_cap_lop, cs.ten_co_so
                FROM lop_hoc l
                JOIN mon_hoc m ON m.id=l.mon_hoc_id
                JOIN cap_lop cl ON cl.id=l.cap_lop_id
                LEFT JOIN co_so cs ON cs.id=l.co_so_id
                WHERE l.id=? AND l.deleted_at IS NULL", [$objId]);
            if (!$row) return [];
            return [
                ['Lớp học', $row['ma_lop'].' — '.$row['ten_lop']],
                ['Môn · Khối', $row['ten_mon'].' · '.$row['ten_cap_lop']],
                ['Hình thức / Cơ sở', $row['hinh_thuc'] === 'online' ? 'Trực tuyến' : ($row['ten_co_so'] ?: 'Trực tiếp')],
                ['Ngày bắt đầu', self::formatDate($row['ngay_bat_dau'])],
            ];
        }

        if ($objType === 'hoa_don' || $objType === 'thanh_toan') {
            if (!in_array($role, ['PHU_HUYNH', 'ADMIN', 'GIAO_VU'], true)) {
                throw new \DomainException('Không gửi thông tin học phí cho vai trò không được phép.');
            }
            $invoiceId = $objId;
            $receipt = null;
            if ($objType === 'thanh_toan') {
                $receipt = Store::one('SELECT id, hoa_don_id, so_tien, loai, trang_thai, ngay_thanh_toan FROM thanh_toan WHERE id=?', [$objId]);
                if (!$receipt) return [];
                if ($receipt['trang_thai'] !== 'hop_le') {
                    throw new \DomainException('Phiếu thu không còn ở trạng thái hợp lệ.');
                }
                $invoiceId = (int)$receipt['hoa_don_id'];
            }
            $inv = Store::one("SELECT hd.id, hd.ma_hd, hd.phai_thu, hd.da_thu, hd.trang_thai, hd.han_thanh_toan,
                    fn_phu_huynh_hoa_don(hd.id) AS owner_parent_id,
                    k.ten AS ten_khoa, hv.ho_ten AS ten_hoc_vien
                FROM hoa_don hd
                LEFT JOIN dang_ky_khoa_hoc dk ON dk.id=hd.dang_ky_khoa_hoc_id
                LEFT JOIN khoa_hoc k ON k.id=dk.khoa_hoc_id
                LEFT JOIN hoc_vien hv ON hv.id=dk.hoc_vien_id
                WHERE hd.id=?", [$invoiceId]);
            if (!$inv) return [];
            if ($role === 'PHU_HUYNH' && (int)$inv['owner_parent_id'] !== $userId) {
                throw new \DomainException('Phụ huynh nhận email không khớp chủ sở hữu hóa đơn.');
            }
            if (in_array($inv['trang_thai'], ['nhap', 'huy'], true)) {
                throw new \DomainException('Hóa đơn ở trạng thái nháp/hủy không được gửi email.');
            }
            $invStatus = [
                'chua_thanh_toan' => 'Chưa thanh toán',
                'mot_phan' => 'Thanh toán một phần',
                'da_thanh_toan' => 'Đã thanh toán đủ',
            ][$inv['trang_thai']] ?? $inv['trang_thai'];
            $remaining = max(0, (float)$inv['phai_thu'] - (float)$inv['da_thu']);
            $rows = [
                ['Mã hóa đơn', $inv['ma_hd']],
                ['Học viên', $inv['ten_hoc_vien'] ?: null],
                ['Khóa học', $inv['ten_khoa'] ?: null],
                ['Số phải thu', self::formatMoney($inv['phai_thu'])],
                ['Đã ghi nhận', self::formatMoney($inv['da_thu'])],
                ['Còn lại', self::formatMoney($remaining)],
                ['Trạng thái', $invStatus],
            ];
            if ($receipt) {
                $rows[] = ['Phiếu thu xác nhận', self::formatMoney($receipt['so_tien']).' · '.self::formatDate($receipt['ngay_thanh_toan'])];
            } elseif ($inv['han_thanh_toan']) {
                $rows[] = ['Hạn thanh toán', self::formatDate($inv['han_thanh_toan'])];
            }
            return $rows;
        }

        if ($objType === 'don_xin_nghi') {
            $row = Store::one("SELECT d.trang_thai, d.ly_do_tu_choi, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, l.ma_lop, l.ten_lop
                FROM don_xin_nghi d JOIN buoi_hoc b ON b.id=d.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id WHERE d.id=?", [$objId]);
            if (!$row) return [];
            $st = ['cho_duyet' => 'Chờ duyệt', 'da_duyet' => 'Đã duyệt', 'tu_choi' => 'Từ chối'][$row['trang_thai']] ?? $row['trang_thai'];
            return [
                ['Lớp · Ca học', $row['ma_lop'].' · '.self::formatDate($row['ngay']).' '.self::formatTime($row['gio_bat_dau']).'–'.self::formatTime($row['gio_ket_thuc'])],
                ['Kết quả duyệt', $st],
                ['Phản hồi', $row['ly_do_tu_choi'] ?: null],
            ];
        }

        if ($objType === 'yeu_cau_nghi_lop') {
            $row = Store::one("SELECT r.trang_thai, r.ghi_chu, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, l.ma_lop, l.ten_lop
                FROM yeu_cau_nghi_lop r JOIN buoi_hoc b ON b.id=r.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id WHERE r.id=?", [$objId]);
            if (!$row) return [];
            $st = ['cho_duyet' => 'Chờ duyệt', 'da_duyet' => 'Đã duyệt', 'tu_choi' => 'Từ chối'][$row['trang_thai']] ?? $row['trang_thai'];
            return [
                ['Lớp · Ca học', $row['ma_lop'].' — '.$row['ten_lop'].' ('.self::formatDate($row['ngay']).' '.self::formatTime($row['gio_bat_dau']).'–'.self::formatTime($row['gio_ket_thuc']).')'],
                ['Kết quả duyệt', $st],
                ['Ghi chú Admin', $row['ghi_chu'] ?: null],
            ];
        }

        if ($objType === 'dieu_chinh_cong') {
            $row = Store::one("SELECT d.trang_thai, d.ghi_chu_duyet, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, l.ma_lop
                FROM dieu_chinh_cong d JOIN phan_cong_buoi_hoc pc ON pc.id=d.phan_cong_id
                JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id WHERE d.id=?", [$objId]);
            if (!$row) return [];
            $st = ['cho_duyet' => 'Chờ duyệt', 'da_duyet' => 'Đã duyệt', 'tu_choi' => 'Từ chối'][$row['trang_thai']] ?? $row['trang_thai'];
            return [
                ['Lớp · Ca học', $row['ma_lop'].' · '.self::formatDate($row['ngay']).' '.self::formatTime($row['gio_bat_dau']).'–'.self::formatTime($row['gio_ket_thuc'])],
                ['Kết quả duyệt', $st],
                ['Phản hồi Admin', $row['ghi_chu_duyet'] ?: null],
            ];
        }

        if ($objType === 'ung_tuyen_ca') {
            $row = Store::one("SELECT uc.trang_thai, uc.ly_do_tu_choi, d.ma_de_xuat, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, l.ma_lop
                FROM ung_tuyen_ca uc JOIN de_xuat_tuyen_ca d ON d.id=uc.de_xuat_id
                JOIN buoi_hoc b ON b.id=d.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id WHERE uc.id=?", [$objId]);
            if (!$row) return [];
            $st = ['cho_duyet' => 'Chờ duyệt', 'da_duyet' => 'Đã duyệt', 'tu_choi' => 'Từ chối', 'da_huy' => 'Đã hủy'][$row['trang_thai']] ?? $row['trang_thai'];
            return [
                ['Đề xuất', $row['ma_de_xuat'].' · '.$row['ma_lop']],
                ['Ca học', self::formatDate($row['ngay']).' '.self::formatTime($row['gio_bat_dau']).'–'.self::formatTime($row['gio_ket_thuc'])],
                ['Trạng thái ứng tuyển', $st],
                ['Lý do', $row['ly_do_tu_choi'] ?: null],
            ];
        }

        return [];
    }
}
