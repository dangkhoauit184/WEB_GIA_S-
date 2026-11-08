<?php
namespace App\Modules\Academic\Features\Scheduling;

use App\Shared\Input;

final class SchedulingValidator
{
    public static function weekly(array $input): array
    {
        $input+=['isActive'=>true];
        $data=Input::validate($input,['lopId'=>['id',true],'thu'=>['id',true],'gioBatDau'=>['time',true],
            'gioKetThuc'=>['time',true],'phongHoc'=>['string',false],'hieuLucTu'=>['date',true],
            'hieuLucDen'=>['date',false],'isActive'=>['bool',true]]);
        if($data['thu']<2 || $data['thu']>8) Input::fail('thu');
        Input::pair($data,'gioBatDau','gioKetThuc');
        if(isset($data['hieuLucDen']) && $data['hieuLucDen']<$data['hieuLucTu']) Input::fail('hieuLucDen');
        return $data;
    }
    public static function generate(array $input): array
    {
        $data=Input::validate($input,['lopId'=>['id',true],'tuNgay'=>['date',true],'denNgay'=>['date',true]]);
        if($data['denNgay']<$data['tuNgay']) Input::fail('denNgay');
        if((new \DateTimeImmutable($data['tuNgay']))->diff(new \DateTimeImmutable($data['denNgay']))->days>366) Input::fail('denNgay (tối đa 366 ngày)');
        return $data;
    }
    public static function assignment(array $input): array
    {
        $input+=['vaiTro'=>'tro_giang','donGia'=>0,'cachTinhLuong'=>'theo_buoi'];
        return Input::validate($input,['userId'=>['id',true],'vaiTro'=>[['day_chinh','tro_giang'],true],
            'donGia'=>['number',true],'cachTinhLuong'=>[['theo_buoi','theo_gio'],true]]);
    }
    public static function session(array $input): array
    {
        $data=Input::validate($input,['ngay'=>['date',true],'gioBatDau'=>['time',true],'gioKetThuc'=>['time',true],
            'phongHoc'=>['string',false],'coSoId'=>['id',false],'chuDeBaiHoc'=>['string',false],
            'noiDungBaiHoc'=>['string',false],'fileBaiGiangUrl'=>['string',false],
            'trangThai'=>[['du_kien','dang_day','da_day','huy','doi'],true],
            'huyBoi'=>[['trung_tam','giao_vien','gia_su','hoc_vien','khac'],false],'lyDoHuyDoi'=>['string',false]],false);
        Input::pair($data,'gioBatDau','gioKetThuc');
        return $data;
    }
}
