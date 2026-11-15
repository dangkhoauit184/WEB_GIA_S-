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
}
