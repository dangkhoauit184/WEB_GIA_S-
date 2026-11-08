<?php
namespace App\Modules\Administration\Features\Auth;

use App\Shared\Access;

final class AuthModel extends \CodeIgniter\Model
{
    public static function map(array $row, array $permissions = []): array
    {
        $map = ['accountId'=>'account_id','userId'=>'user_id','username'=>'username','status'=>'status',
            'groupId'=>'group_id','groupName'=>'group_name','hoTen'=>'hoten','sdt'=>'sdt','email'=>'email',
            'ngaySinh'=>'ngaysinh','diaChi'=>'diachi','ngheNghiep'=>'nghe_nghiep','khuVucId'=>'khu_vuc_id',
            'nhanVienId'=>'nhan_vien_id','maNv'=>'ma_nv','chucVu'=>'chuc_vu','coSoId'=>'co_so_id',
            'nhanSuId'=>'nhan_su_id','maNs'=>'ma_ns','phanLoai'=>'phan_loai'];
        $out = [];
        foreach ($map as $key=>$column) $out[$key] = $row[$column] ?? null;
        $out['role'] = Access::role($row['group_name'], $row['phan_loai'] ?? '');
        $out['permissions'] = $permissions;
        return $out;
    }
}
