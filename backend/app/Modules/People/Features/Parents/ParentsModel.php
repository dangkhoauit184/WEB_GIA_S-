<?php
namespace App\Modules\People\Features\Parents;

final class ParentsModel extends \CodeIgniter\Model
{
    public static function map(array $row, array $extras = []): array
    {
        $fields = [
            'userId'=>'user_id',
            'hoTen'=>'hoten',
            'sdt'=>'sdt',
            'email'=>'email',
            'ngaySinh'=>'ngaysinh',
            'diaChi'=>'diachi',
            'ngheNghiep'=>'nghe_nghiep',
            'khuVucId'=>'khu_vuc_id',
            'tenKhuVuc'=>'ten_khu_vuc',
            'accountId'=>'account_id',
            'username'=>'username',
            'accountStatus'=>'account_status',
            'soCon'=>'so_con',
            'createdAt'=>'created_at'
        ];
        $out = [];
        foreach ($fields as $key=>$column) $out[$key] = $row[$column] ?? null;
        if ($out['soCon'] !== null) $out['soCon'] = (int)$out['soCon'];
        return array_merge($out, $extras);
    }
}
