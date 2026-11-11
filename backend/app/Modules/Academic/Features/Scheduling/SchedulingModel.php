<?php
namespace App\Modules\Academic\Features\Scheduling;

final class SchedulingModel extends \CodeIgniter\Model
{
    private static function fields(array $row,array $fields): array
    {
        $out=[];
        foreach($fields as $key=>$column) $out[$key]=$row[$column]??null;
        return $out;
    }
    public static function weekly(array $row): array
    {
        $data=self::fields($row,['id'=>'id','lopId'=>'lop_id','maLop'=>'ma_lop','tenLop'=>'ten_lop','thu'=>'thu',
            'gioBatDau'=>'gio_bat_dau','gioKetThuc'=>'gio_ket_thuc','phongHoc'=>'phong_hoc',
            'hieuLucTu'=>'hieu_luc_tu','hieuLucDen'=>'hieu_luc_den','isActive'=>'is_active']);
        $data['isActive']=(bool)$data['isActive']; $data['thu']=(int)$data['thu'];
        return $data;
    }
    public static function session(array $row): array
    {
        return self::fields($row,['id'=>'id','lopId'=>'lop_id','maLop'=>'ma_lop','tenLop'=>'ten_lop',
            'lichHocId'=>'lich_hoc_id','loai'=>'loai','buoiGocId'=>'buoi_goc_id','ngay'=>'ngay',
            'gioBatDau'=>'gio_bat_dau','gioKetThuc'=>'gio_ket_thuc','coSoId'=>'co_so_id','phongHoc'=>'phong_hoc',
            'chuDeBaiHoc'=>'chu_de_bai_hoc','noiDungBaiHoc'=>'noi_dung_bai_hoc','fileBaiGiangUrl'=>'file_bai_giang_url',
            'trangThai'=>'trang_thai','huyBoi'=>'huy_boi','lyDoHuyDoi'=>'ly_do_huy_doi','nguoiHuyId'=>'nguoi_huy_id',
            'huyLuc'=>'huy_luc','nguoiDayChinhUserId'=>'day_chinh_user_id','tenNguoiDayChinh'=>'ten_nguoi_day_chinh',
            'troGiangIds'=>'tro_giang_ids','tenTroGiang'=>'ten_tro_giang','phamViBu'=>'pham_vi_bu','hocBuIds'=>'hoc_bu_ids']);
    }
}
