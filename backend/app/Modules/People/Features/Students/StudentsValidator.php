<?php
namespace App\Modules\People\Features\Students;

use App\Shared\Input;

final class StudentsValidator
{
    public static function validate(array $input, bool $create = true): array
    {
        $rules = [
            'phuHuynhUserId'=>['id',true], 'maHv'=>['string',true], 'hoTen'=>['string',true],
            'ngaySinh'=>['date',false], 'gioiTinh'=>[['nam','nu','khac'],false],
            'truong'=>['string',false], 'capLopId'=>['id',false], 'diaChi'=>['string',false],
            'khuVucId'=>['id',false], 'ghiChu'=>['string',false],
            'trangThai'=>[['dang_hoc','tam_nghi','hoan_thanh','nghi_han'],true,'dang_hoc'],
        ];
        if ($create && !isset($input['trangThai'])) $input['trangThai'] = 'dang_hoc';
        if (!$create) unset($rules['maHv']);
        return Input::validate($input, $rules, $create);
    }
}
