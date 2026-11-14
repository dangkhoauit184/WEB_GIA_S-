<?php
namespace App\Modules\Communication\Features\Notifications;

final class NotificationsModel extends \CodeIgniter\Model
{
    public static function map(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'userId' => (int)$row['user_id'],
            'loai' => $row['loai'],
            'tieuDe' => $row['tieu_de'],
            'noiDung' => $row['noi_dung'],
            'doiTuongLoai' => $row['doi_tuong_loai'] ?? null,
            'doiTuongId' => isset($row['doi_tuong_id']) ? (int)$row['doi_tuong_id'] : null,
            'daDoc' => (bool)($row['da_doc'] ?? ($row['doc_luc'] !== null)),
            'docLuc' => $row['doc_luc'],
            'createdAt' => $row['created_at'],
        ];
    }
}
