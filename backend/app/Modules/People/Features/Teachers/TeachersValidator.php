<?php
namespace App\Modules\People\Features\Teachers;

use App\Shared\Input;

final class TeachersValidator
{
    public static function validate(array $input,bool $create=true,string $defaultType='giao_vien'): array
    {
        if ($create) $input += ['phanLoai'=>$defaultType,'hinhThucDay'=>'offline','ngheNghiep'=>'giao_vien',
            'trangThai'=>'hoat_dong','kinhNghiemNam'=>0,'donGiaGioChuan'=>0,'monDays'=>[],'khuVucIds'=>[],'lichRanhs'=>[]];
        foreach (['phanLoai'=>['giao_vien','gia_su','tro_giang'],'hinhThucDay'=>['online','offline']] as $field=>$allowed) {
            if (array_key_exists($field,$input)) $input[$field]=Input::set($input[$field],$allowed);
        }
        $rules=['maNs'=>['string',true],'hoTen'=>['string',true],'email'=>['email',true],
            'sdt'=>['string',false],'ngaySinh'=>['date',false],'diaChi'=>['string',false],
            'phanLoai'=>['string',true],'gioiTinh'=>[['nam','nu','khac'],false],'cccd'=>['string',false],
            'ngheNghiep'=>[['sinh_vien','giao_vien','cu_nhan','tu_do'],true],'donViCongTac'=>['string',false],
            'gioiThieu'=>['string',false],'kinhNghiemNam'=>['integer',true],'hinhThucDay'=>['string',true],
            'donGiaGioChuan'=>['number',true],'nganHang'=>['string',false],'soTaiKhoan'=>['string',false],
            'chuTaiKhoan'=>['string',false],'trangThai'=>[['hoat_dong','tam_nghi','ngung_hop_tac'],true],
            'monDays'=>['array',true],'khuVucIds'=>['array',true],'lichRanhs'=>['array',true]];
        if ($create) $rules+=['username'=>['string',false],'password'=>['password',false]];
        else unset($rules['maNs']);
        $data=Input::validate($input,$rules,$create);
        if ($create) Input::credentials($data);
        if (isset($data['monDays'])) {
            foreach ($data['monDays'] as &$item) {
                if (!is_array($item)) Input::fail('monDays');
                $item=Input::validate($item,['monHocId'=>['id',true],'capLopTu'=>['id',true],
                    'capLopDen'=>['id',true],'hocPhiBuoiDeXuat'=>['number',false,0]]);
                if ($item['capLopDen']<$item['capLopTu']) Input::fail('capLopDen');
            }
        }
        if (isset($data['khuVucIds'])) foreach ($data['khuVucIds'] as &$id) $id=Input::validate(['id'=>$id],['id'=>['id',true]])['id'];
        if (isset($data['lichRanhs'])) {
            foreach ($data['lichRanhs'] as &$item) {
                if (!is_array($item)) Input::fail('lichRanhs');
                $item=Input::validate($item,['thu'=>['id',true],'gioBatDau'=>['time',true],'gioKetThuc'=>['time',true]]);
                if ($item['thu']<2 || $item['thu']>8) Input::fail('thu');
                Input::pair($item,'gioBatDau','gioKetThuc');
            }
        }
        return $data;
    }

    public static function match(array $input): array
    {
        $data=Input::validate($input,['monHocId'=>['id',true],'capLopId'=>['id',true],
            'khuVucId'=>['id',false],'nganSachMax'=>['number',false],'thu'=>['id',false],
            'gioBatDau'=>['time',false],'gioKetThuc'=>['time',false]]);
        if (isset($data['thu']) || isset($data['gioBatDau']) || isset($data['gioKetThuc'])) {
            if (!isset($data['thu'],$data['gioBatDau'],$data['gioKetThuc']) || $data['thu']<2 || $data['thu']>8) Input::fail('lichRanh');
            Input::pair($data,'gioBatDau','gioKetThuc');
        }
        return $data;
    }
}
