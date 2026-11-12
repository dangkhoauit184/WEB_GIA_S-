<?php
namespace App\Modules\People\Features\Students;

final class StudentsModel extends \CodeIgniter\Model
{
    public static function map(array $row, array $extras = []): array
    {
        $fields = [
            'id'=>'id',
            'maHv'=>'ma_hv',
            'hoTen'=>'ho_ten',
            'ngaySinh'=>'ngay_sinh',
            'gioiTinh'=>'gioi_tinh',
            'truong'=>'truong',
            'capLopId'=>'cap_lop_id',
            'tenCapLop'=>'ten_cap_lop',
            'tenKhoa'=>'ten_khoa',
            'diaChi'=>'dia_chi',
            'khuVucId'=>'khu_vuc_id',
            'tenKhuVuc'=>'ten_khu_vuc',
            'ghiChu'=>'ghi_chu',
            'trangThai'=>'trang_thai',
            'trangThaiHocTap'=>'trang_thai_hoc_tap',
            'phuHuynhUserId'=>'phu_huynh_user_id',
            'tenPhuHuynh'=>'ten_phu_huynh',
            'sdtPhuHuynh'=>'sdt_phu_huynh',
            'emailPhuHuynh'=>'email_phu_huynh',
            'createdAt'=>'created_at',
            'updatedAt'=>'updated_at'
        ];
        $out = [];
        foreach ($fields as $key=>$column) $out[$key] = $row[$column] ?? null;

        return array_merge($out, $extras);
    }
}
