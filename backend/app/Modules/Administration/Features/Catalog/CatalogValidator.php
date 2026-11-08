<?php
namespace App\Modules\Administration\Features\Catalog;

use App\Shared\{ApiException,Input};

final class CatalogValidator
{
    public static function validate(string $resource,array $body,bool $create=true): array
    {
        CatalogModel::definition($resource);
        $rules=match($resource) {
            'subjects'=>['ma'=>['string',true],'ten'=>['string',true],'isActive'=>['bool',true]],
            'regions'=>['ten'=>['string',true],'thanhPho'=>['string',true],'isActive'=>['bool',true]],
            'campuses'=>['maCoSo'=>['string',true],'tenCoSo'=>['string',true],'diaChi'=>['string',true],
                'soDienThoai'=>['string',false],'email'=>['email',false],'khuVucId'=>['id',false],
                'quanLyId'=>['id',false],'isActive'=>['bool',true]],
            'grade-levels'=>['ten'=>['string',true]],
        };
        if($resource==='grade-levels' && $create) throw new ApiException(405,'Cấp lớp dùng danh mục cố định 1–12. Chỉ cập nhật tên.','METHOD_NOT_ALLOWED');
        if(array_diff(array_keys($body),array_keys($rules)) || (!$create && !$body)) Input::fail('fields');
        if($create) {
            $body+=['isActive'=>true];
            if($resource==='regions') $body+=['thanhPho'=>'TP. Hồ Chí Minh'];
        }
        $data=Input::validate($body,$rules,$create);
        $lengths=['ma'=>20,'ten'=>$resource==='grade-levels'?30:100,'thanhPho'=>100,
            'maCoSo'=>20,'tenCoSo'=>150,'diaChi'=>255,'soDienThoai'=>20,'email'=>150];
        foreach($lengths as $field=>$max) if(isset($data[$field]) && mb_strlen($data[$field])>$max) Input::fail($field);
        return $data;
    }

    public static function filters(array $query): array
    {
        $data=Input::validate($query,['q'=>['string',false]],false);
        if(isset($data['q']) && mb_strlen($data['q'])>100) Input::fail('q');
        if(array_key_exists('isActive',$query)) {
            if(!in_array($query['isActive'],['0','1',0,1],true)) Input::fail('isActive');
            $data['isActive']=(int)$query['isActive'];
        }
        return $data;
    }

    public static function setting(string $key,array $body): string
    {
        $range=match($key) {
            'checkin_truoc_phut','checkin_tre_sau_phut'=>[0,1440],
            'han_nhap_bao_cao_gio'=>[1,8760],
            default=>throw new ApiException(404,'Không tìm thấy cấu hình được hỗ trợ.','SETTING_NOT_FOUND'),
        };
        if(array_diff(array_keys($body),['giaTri'])) Input::fail('fields');
        $data=Input::validate($body,['giaTri'=>['integer',true]]);
        if($data['giaTri']<$range[0] || $data['giaTri']>$range[1]) Input::fail('giaTri');
        return (string)$data['giaTri'];
    }
}
