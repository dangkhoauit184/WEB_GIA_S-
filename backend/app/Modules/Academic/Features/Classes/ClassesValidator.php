<?php
namespace App\Modules\Academic\Features\Classes;

use App\Shared\Input;

final class ClassesValidator
{
    public static function validate(array $input,bool $create=true): array
    {
        if($create) $input+=['loaiLop'=>'kem_1_1','hinhThuc'=>'offline','siSoToiThieu'=>1,'siSoToiDa'=>1,
            'cachThuPhi'=>'theo_buoi','hocPhiBuoi'=>0,'hocPhiKhoa'=>0,'mucTraChinh'=>0,'mucTraTroGiang'=>0,
            'cachTinhLuong'=>'theo_buoi','trangThai'=>'sap_bat_dau'];
        $rules=['maLop'=>['string',true],'tenLop'=>['string',true],'loaiLop'=>[['kem_1_1','kem_1_2','nhom'],true],'khoaHocId'=>['id',false],
            'deXuatId'=>['id',false],'monHocId'=>['id',true],'capLopId'=>['id',true],'nguoiDayChinhId'=>['id',true],
            'coSoId'=>['id',false],'phongHoc'=>['string',false],'hinhThuc'=>[['online','offline'],true],
            'diaDiem'=>['string',false],'linkHoc'=>['string',false],'khuVucId'=>['id',false],
            'siSoToiThieu'=>['id',true],'siSoToiDa'=>['id',true],'cachThuPhi'=>[['theo_buoi','theo_thang','theo_khoa'],true],
            'hocPhiBuoi'=>['number',true],'hocPhiThang'=>['number',false],'hocPhiKhoa'=>['number',true],
            'mucTraChinh'=>['number',true],'mucTraTroGiang'=>['number',true],'cachTinhLuong'=>[['theo_buoi','theo_gio'],true],
            'soBuoiDuKien'=>['id',false],'ngayBatDau'=>['date',true],'ngayKetThuc'=>['date',false],
            'trangThai'=>[['sap_bat_dau','dang_hoc','tam_dung','hoan_thanh','huy'],true],'nhanVienId'=>['id',false]];
        if(!$create) unset($rules['maLop'],$rules['nhanVienId'],$rules['deXuatId']);
        $data=Input::validate($input,$rules,$create);
        if(isset($data['siSoToiThieu'],$data['siSoToiDa']) && $data['siSoToiDa']<$data['siSoToiThieu']) Input::fail('siSoToiDa');
        if(isset($data['ngayBatDau'],$data['ngayKetThuc']) && $data['ngayKetThuc']<$data['ngayBatDau']) Input::fail('ngayKetThuc');
        return $data;
    }
    public static function enrollment(array $input,bool $create=true): array
    {
        if($create) $input+=['ngayVao'=>date('Y-m-d'),'trangThai'=>'dang_hoc'];
        $rules=['hocVienId'=>['id',true],'ngayVao'=>['date',true],'ngayRoi'=>['date',false],
            'trangThai'=>[['dang_hoc','tam_nghi','da_roi','cho_xep_lop','hoc_thu'],true]];
        if(!$create) unset($rules['hocVienId'],$rules['ngayVao']);
        return Input::validate($input,$rules,$create);
    }
}
