<?php
namespace App\Modules\Administration\Features\Staff;

use App\Shared\Input;

final class StaffValidator
{
    public static function create(array $body): array
    {
        $rules=['hoTen'=>['string',true],'email'=>['email',true],'sdt'=>['string',true],
            'role'=>[['Admin','NhanVien','giao_vien','gia_su','tro_giang'],true],
            'username'=>['string',true],'password'=>['password',true],
            'donGiaGio'=>['integer',false,0],'gioiThieu'=>['string',false]];
        if(array_diff(array_keys($body),array_keys($rules))) Input::fail('fields');
        $data=Input::validate($body,$rules);
        if(mb_strlen($data['hoTen'])<2) Input::fail('hoTen');
        foreach(['hoTen'=>150,'email'=>150,'username'=>100] as $field=>$max) if(mb_strlen($data[$field])>$max) Input::fail($field);
        if(!preg_match('/^0[35789]\d{8}$/D',$data['sdt'])) Input::fail('sdt');
        if($data['donGiaGio']===null || $data['donGiaGio']>999999999999) Input::fail('donGiaGio');
        if(in_array($data['role'],['Admin','NhanVien'],true) && $data['donGiaGio']!==0) Input::fail('donGiaGio');
        if(isset($data['gioiThieu']) && strlen($data['gioiThieu'])>65535) Input::fail('gioiThieu');
        return $data;
    }
}
