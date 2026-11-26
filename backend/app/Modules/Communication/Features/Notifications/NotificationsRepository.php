<?php
namespace App\Modules\Communication\Features\Notifications;

use App\Shared\Store;

final class NotificationsRepository
{
    public static function forUser(int $userId): array
    {
        $rows = Store::rows("SELECT id, user_id, loai, tieu_de, noi_dung, doi_tuong_loai, doi_tuong_id, da_doc, doc_luc, created_at
            FROM thong_bao
            WHERE user_id=?
            ORDER BY id DESC LIMIT 100", [$userId]);
        return array_map([NotificationsModel::class, 'map'], $rows);
    }

    public static function markRead(int $id, int $userId): bool
    {
        $row = Store::one('SELECT id FROM thong_bao WHERE id=? AND user_id=?', [$id, $userId]);
        if (!$row) {
            throw new \App\Shared\ApiException(404, 'Không tìm thấy thông báo.', 'NOTIFICATION_NOT_FOUND');
        }
        Store::execute("UPDATE thong_bao SET doc_luc=COALESCE(doc_luc, NOW()) WHERE id=? AND user_id=?", [$id, $userId]);
        return true;
    }

    public static function markAllRead(int $userId): int
    {
        return Store::execute("UPDATE thong_bao SET doc_luc=NOW() WHERE user_id=? AND doc_luc IS NULL", [$userId]);
    }

    public static function create(array $data): array
    {
        $id = Store::insert('thong_bao', [
            'user_id' => $data['userId'],
            'loai' => $data['loai'] ?? 'he_thong',
            'tieu_de' => $data['tieuDe'],
            'noi_dung' => $data['noiDung'] ?? null,
            'doi_tuong_loai' => $data['doiTuongLoai'] ?? null,
            'doi_tuong_id' => $data['doiTuongId'] ?? null,
        ]);
        $row = Store::one('SELECT * FROM thong_bao WHERE id=?', [$id]);
        return NotificationsModel::map($row);
    }

    /**
     * Single lightweight Admin notification bell summary (replaces 5 heavy full-list API calls).
     */
    public static function adminSummary(): array
    {
        $items = [];

        $txs = Store::rows(
            "SELECT gd.id, gd.so_tien, gd.created_at, hd.ma_hd, u.hoten AS ten_phu_huynh
             FROM giao_dich_truc_tuyen gd
             JOIN hoa_don hd ON hd.id = gd.hoa_don_id
             JOIN users u ON u.user_id = gd.phu_huynh_id
             WHERE gd.trang_thai = 'cho_xac_nhan'
             ORDER BY gd.id DESC LIMIT 20"
        );
        foreach ($txs as $r) {
            $items[] = [
                'id' => 'tx-' . $r['id'],
                'timestamp' => (string)$r['created_at'],
                'title' => 'Phụ huynh ' . ($r['ten_phu_huynh'] ?? '') . ' gửi thanh toán',
                'desc' => 'Giao dịch GD-' . $r['id'] . ' (' . number_format((int)$r['so_tien'], 0, ',', '.') . ' ₫) cho hóa đơn ' . ($r['ma_hd'] ?? '') . ' đang chờ đối soát.',
                'screen' => 'admin-payments',
            ];
        }

        $regs = Store::rows(
            "SELECT dk.id, dk.ngay_dang_ky AS created_at, hv.ho_ten, k.ten AS ten_khoa, k.ma AS ma_khoa
             FROM dang_ky_khoa_hoc dk
             JOIN hoc_vien hv ON hv.id = dk.hoc_vien_id AND hv.deleted_at IS NULL
             JOIN khoa_hoc k ON k.id = dk.khoa_hoc_id
             WHERE dk.trang_thai = 'cho_xep_lop'
             ORDER BY dk.id DESC LIMIT 20"
        );
        foreach ($regs as $r) {
            $items[] = [
                'id' => 'reg-' . $r['id'],
                'timestamp' => (string)$r['created_at'],
                'title' => 'Đăng ký khóa học mới DK-' . $r['id'],
                'desc' => 'Học viên ' . ($r['ho_ten'] ?? '') . ' đăng ký khóa ' . ($r['ten_khoa'] ?: ($r['ma_khoa'] ?? '')) . ' đang chờ Admin xếp lớp.',
                'screen' => 'admin-courses',
            ];
        }

        $corrs = Store::rows(
            "SELECT dc.id, dc.loai, dc.ly_do, dc.created_at, b.ngay, l.ma_lop, u.hoten,
                    CASE
                      WHEN dc.loai = 'huy_cong' THEN 0
                      WHEN dc.checkin_moi IS NOT NULL AND dc.checkout_moi IS NOT NULL
                        THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, GREATEST(dc.checkin_moi, TIMESTAMP(b.ngay, b.gio_bat_dau)), LEAST(dc.checkout_moi, TIMESTAMP(b.ngay, b.gio_ket_thuc))))
                      ELSE NULL
                    END AS phut_de_xuat
             FROM dieu_chinh_cong dc
             JOIN phan_cong_buoi_hoc pc ON pc.id = dc.phan_cong_id
             JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
             JOIN lop_hoc l ON l.id = b.lop_id
             JOIN users u ON u.user_id = pc.user_id
             WHERE dc.trang_thai = 'cho_duyet'
             ORDER BY dc.id DESC LIMIT 20"
        );
        foreach ($corrs as $r) {
            $hText = $r['loai'] === 'huy_cong'
                ? '0 giờ'
                : ($r['phut_de_xuat'] !== null ? round(((int)$r['phut_de_xuat']) / 60, 2) . ' giờ' : 'điều chỉnh giờ');
            $items[] = [
                'id' => 'corr-' . $r['id'],
                'timestamp' => (string)($r['created_at'] ?: $r['ngay']),
                'title' => ($r['hoten'] ?: 'Nhân sự') . ' gửi đơn điều chỉnh giờ công',
                'desc' => 'Lớp ' . ($r['ma_lop'] ?? '') . ' (' . ($r['ngay'] ?? '') . ') · Đề xuất ' . $hText . ': ' . ($r['ly_do'] ?? ''),
                'screen' => 'admin-exceptions',
            ];
        }

        $leaves = Store::rows(
            "SELECT y.id, y.ly_do, b.ngay, l.ma_lop, u.hoten AS nguoi_gui
             FROM yeu_cau_nghi_lop y
             JOIN buoi_hoc b ON b.id = y.buoi_hoc_id
             JOIN lop_hoc l ON l.id = b.lop_id
             JOIN users u ON u.user_id = y.nguoi_gui_id
             WHERE y.trang_thai = 'cho_duyet'
             ORDER BY y.id DESC LIMIT 20"
        );
        foreach ($leaves as $r) {
            $items[] = [
                'id' => 'leave-' . $r['id'],
                'timestamp' => (string)$r['ngay'],
                'title' => ($r['nguoi_gui'] ?: 'Giáo viên') . ' xin nghỉ buổi học ' . ($r['ma_lop'] ?? ''),
                'desc' => 'Ngày ' . (!empty($r['ngay']) ? date('d/m/Y', strtotime((string)$r['ngay'])) : '') . ' · Lý do: ' . ($r['ly_do'] ?: 'Chờ Admin phê duyệt'),
                'screen' => 'admin-exceptions',
            ];
        }

        $proposals = Store::rows(
            "SELECT dx.id, yc.ma_yc, COALESCE(hv.ho_ten, yc.ho_ten_lien_he) AS ten_hv,
                    u_ns.hoten AS ten_nguoi_day,
                    GREATEST(COALESCE(dx.nhan_su_phan_hoi_luc, dx.created_at), COALESCE(dx.phu_huynh_phan_hoi_luc, dx.created_at)) AS ts
             FROM de_xuat_nhan_su dx
             JOIN yeu_cau_hoc yc ON yc.id = dx.yeu_cau_id
             JOIN nhan_su_chuyen_mon ns ON ns.id = dx.nhan_su_id
             JOIN users u_ns ON u_ns.user_id = ns.user_id
             LEFT JOIN hoc_vien hv ON hv.id = yc.hoc_vien_id
             LEFT JOIN lop_hoc l ON l.de_xuat_id = dx.id AND l.deleted_at IS NULL
             WHERE dx.tt_nhan_su = 'dong_y' AND dx.tt_phu_huynh = 'dong_y' AND l.id IS NULL
             ORDER BY dx.id DESC LIMIT 20"
        );
        foreach ($proposals as $r) {
            $items[] = [
                'id' => 'proposal-' . $r['id'],
                'timestamp' => (string)$r['ts'],
                'title' => 'Đề xuất DX-' . $r['id'] . ' đã được hai bên đồng ý',
                'desc' => ($r['ma_yc'] ?? '') . ' · ' . ($r['ten_hv'] ?? '') . ' · ' . ($r['ten_nguoi_day'] ?? '') . '. Chờ Admin xếp lớp.',
                'screen' => 'staff-intake',
            ];
        }

        usort($items, static fn(array $a, array $b): int => strcmp($b['timestamp'], $a['timestamp']));

        return [
            'total' => count($items),
            'items' => array_slice($items, 0, 50),
        ];
    }
}
