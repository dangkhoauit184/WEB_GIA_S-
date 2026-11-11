<?php
namespace App\Modules\Administration\Features\Catalog;

use App\Shared\{ApiException,Input,Store};
use App\Modules\Administration\Features\Permissions\PermissionsRepository;

final class RoomCatalog
{
    // Room labels already use text in classes/sessions. Keep their catalogue in operational configuration.
    public static function all(): array
    {
        CatalogFilter::administer();
        return Store::rows("SELECT MIN(id) AS id,ten FROM (
            SELECT khoa AS id,gia_tri AS ten FROM cau_hinh WHERE khoa LIKE 'phong_hoc.%'
            UNION ALL SELECT CONCAT('class.',id),phong_hoc FROM lop_hoc WHERE phong_hoc IS NOT NULL
            UNION ALL SELECT CONCAT('session.',id),phong_hoc FROM buoi_hoc WHERE phong_hoc IS NOT NULL
            ) rooms GROUP BY ten ORDER BY ten");
    }
    public static function create(array $body): array
    {
        $actor=CatalogFilter::administer();
        if(array_keys($body)!==['ten']) Input::fail('fields');
        $name=Input::validate($body,['ten'=>['string',true]])['ten'];
        if(mb_strlen($name)>50) Input::fail('ten');
        return Store::transaction(function() use($name,$actor) {
            PermissionsRepository::lockAdministrator($actor);
            if(Store::one("SELECT khoa FROM cau_hinh WHERE khoa LIKE 'phong_hoc.%' AND gia_tri=?",[$name])) throw new ApiException(409,'Phòng học đã có trong danh mục.');
            $key='phong_hoc.'.substr(hash('sha256',mb_strtolower($name)),0,40);
            Store::insert('cau_hinh',['khoa'=>$key,'gia_tri'=>$name,'mo_ta'=>'Danh mục nhãn phòng học sử dụng khi xếp lịch']);
            Store::recordAudit((int)$actor['userId'],'them','cau_hinh',null,null,['khoa'=>$key,'ten'=>$name]);
            return ['id'=>$key,'ten'=>$name];
        });
    }
}
