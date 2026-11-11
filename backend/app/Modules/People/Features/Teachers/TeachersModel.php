<?php
namespace App\Modules\People\Features\Teachers;

final class TeachersModel extends \CodeIgniter\Model
{
    public static function map(array $row, array $extras = []): array
    {
        $fields = [
            'id'=>'id',
            'userId'=>'user_id',
            'maNs'=>'ma_ns',
            'phanLoai'=>'phan_loai',
            'hoTen'=>'hoten',
            'sdt'=>'sdt',
            'email'=>'email',
            'ngaySinh'=>'ngaysinh',
            'diaChi'=>'diachi',
            'gioiTinh'=>'gioi_tinh',
            'cccd'=>'cccd',
            'ngheNghiep'=>'nghe_nghiep_ns',
            'donViCongTac'=>'don_vi_cong_tac',
            'gioiThieu'=>'gioi_thieu',
            'kinhNghiemNam'=>'kinh_nghiem_nam',
            'hinhThucDay'=>'hinh_thuc_day',
            'donGiaGioChuan'=>'don_gia_gio_chuan',
            'nganHang'=>'ngan_hang',
            'soTaiKhoan'=>'so_tai_khoan',
            'chuTaiKhoan'=>'chu_tai_khoan',
            'trangThai'=>'trang_thai',
            'username'=>'username',
            'accountStatus'=>'account_status',
            'createdAt'=>'created_at',
            'updatedAt'=>'updated_at'
        ];
        $out = [];
        foreach ($fields as $key=>$column) $out[$key] = $row[$column] ?? null;
        $out['phanLoai'] = $out['phanLoai'] ? explode(',', $out['phanLoai']) : [];
        if ($out['kinhNghiemNam'] !== null) $out['kinhNghiemNam'] = (float)$out['kinhNghiemNam'];
        $out['hinhThucDay'] = $out['hinhThucDay'] ? explode(',', $out['hinhThucDay']) : [];
        if ($out['donGiaGioChuan'] !== null) $out['donGiaGioChuan'] = (float)$out['donGiaGioChuan'];
        if(\App\Shared\Access::user()['role']!=='ADMIN') unset($out['donGiaGioChuan']);
        return array_merge($out, $extras);
    }
}
