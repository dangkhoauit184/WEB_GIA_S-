<?php
namespace App\Modules\Administration\Features\Catalog;

use App\Shared\{ApiException,Store};

final class CatalogRepository
{
    public static function all(string $resource,array $filters=[]): array
    {
        $definition=CatalogModel::definition($resource); $table=$definition['table'];
        $sql="SELECT * FROM `$table` WHERE 1=1"; $params=[];
        if(isset($filters['isActive']) && isset($definition['fields']['isActive'])) {
            $sql.=' AND is_active=?'; $params[]=$filters['isActive'];
        }
        if(!empty($filters['q'])) {
            $columns=array_intersect_key($definition['fields'],array_flip(['ma','ten','maCoSo','tenCoSo','thanhPho']));
            $sql.=' AND ('.implode(' OR ',array_map(fn($column)=>"`$column` LIKE ?",$columns)).')';
            array_push($params,...array_fill(0,count($columns),'%'.$filters['q'].'%'));
        }
        return Store::rows($sql.' ORDER BY id',$params);
    }

    public static function find(string $resource,int $id,bool $lock=false): array
    {
        $table=CatalogModel::definition($resource)['table'];
        return Store::one("SELECT * FROM `$table` WHERE id=?".($lock?' FOR UPDATE':''),[$id])
            ?? throw new ApiException(404,'Không tìm thấy mục danh mục.','CATALOG_ITEM_NOT_FOUND');
    }

    private static function relations(string $resource,array $data): void
    {
        if($resource!=='campuses') return;
        if(isset($data['khuVucId']) && !Store::one('SELECT id FROM khu_vuc WHERE id=? AND is_active=1',[$data['khuVucId']])) {
            throw new ApiException(400,'Khu vực không tồn tại hoặc đã ngừng sử dụng.');
        }
        if(isset($data['quanLyId']) && !Store::one("SELECT u.user_id FROM users u WHERE u.user_id=? AND u.is_deleted=0
            AND (EXISTS (SELECT 1 FROM nhan_vien nv WHERE nv.user_id=u.user_id AND nv.trang_thai='dang_lam')
            OR EXISTS (SELECT 1 FROM account a JOIN role_group g ON g.group_id=a.group_id WHERE a.user_id=u.user_id
                AND a.status='active' AND a.is_deleted=0 AND g.group_name='Admin' AND g.is_deleted=0))",[$data['quanLyId']])) {
            throw new ApiException(400,'Quản lý cơ sở phải là nhân viên đang làm hoặc quản trị viên đang hoạt động.');
        }
    }

    public static function create(string $resource,array $data,int $actor): array
    {
        return Store::transaction(function() use($resource,$data,$actor) {
            self::relations($resource,$data); $definition=CatalogModel::definition($resource); $values=[];
            foreach($data as $key=>$value) $values[$definition['fields'][$key]]=$value;
            $id=Store::insert($definition['table'],$values); $after=self::find($resource,$id);
            Store::recordAudit($actor,'them',$definition['table'],$id,null,$after);
            return $after;
        });
    }

    public static function update(string $resource,int $id,array $data,int $actor): array
    {
        return Store::transaction(function() use($resource,$id,$data,$actor) {
            $before=self::find($resource,$id,true); self::relations($resource,$data);
            $definition=CatalogModel::definition($resource);
            Store::update($definition['table'],'id',$id,$data,$definition['fields']);
            $after=self::find($resource,$id);
            Store::recordAudit($actor,'sua',$definition['table'],$id,$before,$after);
            return $after;
        });
    }

    public static function settings(): array
    {
        return Store::rows('SELECT khoa,gia_tri AS giaTri,mo_ta AS moTa,updated_at AS updatedAt FROM cau_hinh ORDER BY khoa');
    }

    public static function setting(string $key,string $value,int $actor): array
    {
        return Store::transaction(function() use($key,$value,$actor) {
            $before=Store::one('SELECT * FROM cau_hinh WHERE khoa=? FOR UPDATE',[$key])
                ?? throw new ApiException(404,'Cấu hình chưa được khởi tạo.','SETTING_NOT_FOUND');
            Store::execute('UPDATE cau_hinh SET gia_tri=? WHERE khoa=?',[$value,$key]);
            $after=Store::one('SELECT * FROM cau_hinh WHERE khoa=?',[$key]);
            Store::recordAudit($actor,'sua','cau_hinh',null,$before,$after);
            return ['khoa'=>$key,'giaTri'=>$after['gia_tri'],'moTa'=>$after['mo_ta'],'updatedAt'=>$after['updated_at']];
        });
    }
}
