<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Shared\Input;

final class TuitionValidator
{
    public static function invoice(array $body): array
    {
        if (array_diff(array_keys($body), ['dangKyKhoaHocId', 'hanThanhToan'])) Input::fail('fields');
        return Input::validate($body, ['dangKyKhoaHocId'=>['id', true], 'hanThanhToan'=>['date', false]]);
    }

    public static function receipt(array $body): array
    {
        if (array_diff(array_keys($body), ['soTien', 'phuongThuc', 'maGiaoDich', 'ghiChu'])) Input::fail('fields');
        $data=Input::validate($body, ['soTien'=>['integer', true], 'phuongThuc'=>[['tien_mat','chuyen_khoan'], true],
            'maGiaoDich'=>['string', true], 'ghiChu'=>['string', false]]);
        if (!$data['soTien'] || $data['soTien']>999999999999 || mb_strlen($data['maGiaoDich'])>60 || mb_strlen($data['ghiChu']??'')>255) Input::fail('receipt');
        return $data;
    }
}
