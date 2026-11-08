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
}
