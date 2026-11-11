<?php
namespace App\Modules\Academic\Features\Classes;

final class ClassesModel extends \CodeIgniter\Model
{
    public static function map(array $row, array $extras = []): array
    {
        $fields = [
            'khoaHocId'=>'khoa_hoc_id',
            'id'=>'id',
            'maLop'=>'ma_lop',
            'tenLop'=>'ten_lop',
            'loaiLop'=>'loai_lop',
            'deXuatId'=>'de_xuat_id',
            'monHocId'=>'mon_hoc_id',
            'tenMonHoc'=>'ten_mon_hoc',
            'capLopId'=>'cap_lop_id',
            'tenCapLop'=>'ten_cap_lop',
            'nguoiDayChinhId'=>'nguoi_day_chinh_id',
            'tenNguoiDayChinh'=>'ten_nguoi_day_chinh',
            'coSoId'=>'co_so_id',
            'tenCoSo'=>'ten_co_so',
            'phongHoc'=>'phong_hoc',
            'hinhThuc'=>'hinh_thuc',
            'diaDiem'=>'dia_diem',
            'linkHoc'=>'link_hoc',
            'khuVucId'=>'khu_vuc_id',
            'siSoToiThieu'=>'si_so_toi_thieu',
            'siSoToiDa'=>'si_so_toi_da',
            'siSoHienTai'=>'si_so_hien_tai',
            'cachThuPhi'=>'cach_thu_phi',
            'hocPhiBuoi'=>'hoc_phi_buoi',
            'hocPhiThang'=>'hoc_phi_thang',
            'hocPhiKhoa'=>'hoc_phi_khoa',
            'mucTraChinh'=>'muc_tra_chinh',
            'mucTraTroGiang'=>'muc_tra_tro_giang',
            'cachTinhLuong'=>'cach_tinh_luong',
            'soBuoiDuKien'=>'so_buoi_du_kien',
            'ngayBatDau'=>'ngay_bat_dau',
            'ngayKetThuc'=>'ngay_ket_thuc',
            'trangThai'=>'trang_thai',
            'nhanVienId'=>'nhan_vien_id',
            'createdAt'=>'created_at',
            'updatedAt'=>'updated_at'
        ];
        $out = [];
        foreach ($fields as $key=>$column) $out[$key] = $row[$column] ?? null;
        if ($out['siSoToiThieu'] !== null) $out['siSoToiThieu'] = (int)$out['siSoToiThieu'];
        if ($out['siSoToiDa'] !== null) $out['siSoToiDa'] = (int)$out['siSoToiDa'];
        if ($out['siSoHienTai'] !== null) $out['siSoHienTai'] = (int)$out['siSoHienTai'];
        if ($out['hocPhiBuoi'] !== null) $out['hocPhiBuoi'] = (float)$out['hocPhiBuoi'];
        if ($out['hocPhiThang'] !== null) $out['hocPhiThang'] = (float)$out['hocPhiThang'];
        if ($out['hocPhiKhoa'] !== null) $out['hocPhiKhoa'] = (float)$out['hocPhiKhoa'];
        if ($out['mucTraChinh'] !== null) $out['mucTraChinh'] = (float)$out['mucTraChinh'];
        if ($out['mucTraTroGiang'] !== null) $out['mucTraTroGiang'] = (float)$out['mucTraTroGiang'];
        if ($out['soBuoiDuKien'] !== null) $out['soBuoiDuKien'] = (int)$out['soBuoiDuKien'];
        if(\App\Shared\Access::user()['role']!=='ADMIN') unset($out['mucTraChinh'],$out['mucTraTroGiang']);
        return array_merge($out, $extras);
    }
}
