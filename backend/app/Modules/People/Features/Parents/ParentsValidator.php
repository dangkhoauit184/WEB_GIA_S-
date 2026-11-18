<?php
namespace App\Modules\People\Features\Parents;

use App\Shared\Input;

final class ParentsValidator
{
    public static function validate(array $input, bool $create = true): array
    {
        $rules = ['hoTen'=>['string',true], 'email'=>['email',true], 'sdt'=>['string',false],
            'ngaySinh'=>['date',false], 'diaChi'=>['string',false], 'ngheNghiep'=>['string',false],
            'khuVucId'=>['id',false]];
        if ($create) $rules += ['username'=>['string',false], 'password'=>['password',false]];
        $data = Input::validate($input, $rules, $create);
        if ($create) Input::credentials($data);
        return $data;
    }

    public static function consultation(array $input): array
    {
        return Input::validate($input, [
            'hoTenLienHe' => ['string', true],
            'sdtLienHe' => ['string', true],
            'emailLienHe' => ['email', false],
            'khoaHocQuanTam' => ['string', false],
            'ghiChu' => ['string', false],
            'monHocId' => ['id', false],
            'capLopId' => ['id', false],
        ]);
    }

    public static function leaveRequest(array $input): array
    {
        return Input::validate($input, [
            'hocVienId' => ['id', true],
            'buoiHocId' => ['id', true],
            'lyDo' => ['string', true],
        ]);
    }

    public static function onlinePayment(array $input): array
    {
        return Input::validate($input, [
            'hoaDonId' => ['id', true],
            'nhaCungCap' => ['string', true],
            'soTien' => ['integer', true],
            'xacNhanQr' => ['bool', false],
        ]);
    }

    public static function enrollment(array $input): array
    {
        $data = Input::validate($input, [
            'khoaHocId' => ['id', false],
            'maKhoa' => ['string', false],
            'hocVienId' => ['id', false],
            'hoTenHocVien' => ['string', false],
            'capLopId' => ['id', false],
            'truong' => ['string', false],
        ]);
        if (empty($data['khoaHocId']) && empty($data['maKhoa'])) {
            Input::fail('khoaHocId');
        }
        if (empty($data['hocVienId']) && empty($data['hoTenHocVien'])) {
            Input::fail('hocVienId');
        }
        return $data;
    }
}
