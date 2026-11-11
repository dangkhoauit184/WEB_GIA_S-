<?php
namespace App\Modules\Administration\Features\Catalog;

use App\Shared\ApiException;

final class CatalogModel extends \CodeIgniter\Model
{
    public const RESOURCES = [
        'courses'=>['table'=>'khoa_hoc','fields'=>['id'=>'id','ma'=>'ma','ten'=>'ten','moTa'=>'mo_ta',
            'hocPhi'=>'hoc_phi','isActive'=>'is_active','createdAt'=>'created_at']],
        'subjects'=>['table'=>'mon_hoc','fields'=>['id'=>'id','ma'=>'ma','ten'=>'ten','isActive'=>'is_active']],
        'grade-levels'=>['table'=>'cap_lop','fields'=>['id'=>'id','ten'=>'ten','capHoc'=>'cap_hoc']],
        'regions'=>['table'=>'khu_vuc','fields'=>['id'=>'id','ten'=>'ten','thanhPho'=>'thanh_pho','isActive'=>'is_active']],
        'campuses'=>['table'=>'co_so','fields'=>['id'=>'id','maCoSo'=>'ma_co_so','tenCoSo'=>'ten_co_so',
            'diaChi'=>'dia_chi','soDienThoai'=>'so_dien_thoai','email'=>'email','khuVucId'=>'khu_vuc_id',
            'quanLyId'=>'quan_ly_id','isActive'=>'is_active','createdAt'=>'created_at']],
    ];

    public static function definition(string $resource): array
    {
        return self::RESOURCES[$resource] ?? throw new ApiException(404,'Không tìm thấy danh mục.','CATALOG_NOT_FOUND');
    }

    public static function map(string $resource,array $row): array
    {
        $data=[];
        foreach(self::definition($resource)['fields'] as $key=>$column) $data[$key]=$row[$column];
        if(isset($data['isActive'])) $data['isActive']=(bool)$data['isActive'];
        if(isset($data['hocPhi'])) $data['hocPhi']=(int)$data['hocPhi'];
        return $data;
    }
}
