<?php
namespace App\Modules\Communication\Features\Notifications;

use App\Shared\Input;

final class NotificationsValidator
{
    private const TYPES = [
        'phan_cong','go_phan_cong','lop_moi','doi_lich','nhac_checkin','don_nghi',
        'duyet_don','nhan_xet','hoa_don','chot_cong','de_xuat','he_thong'
    ];

    public static function create(array $body): array
    {
        return Input::validate($body, [
            'userId' => ['id', true],
            'loai' => [self::TYPES, false, 'he_thong'],
            'tieuDe' => ['string', true],
            'noiDung' => ['string', false],
            'doiTuongLoai' => ['string', false],
            'doiTuongId' => ['id', false],
        ]);
    }
}
