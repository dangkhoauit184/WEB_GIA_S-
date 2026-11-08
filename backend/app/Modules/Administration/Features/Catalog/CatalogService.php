<?php
namespace App\Modules\Administration\Features\Catalog;

use App\Shared\ApiException;

final class CatalogService
{
    public static function bootstrap(): array
    {
        CatalogFilter::read(); $data=[];
        foreach(CatalogModel::RESOURCES as $resource=>$definition) $data[$resource]=self::all($resource,['isActive'=>'1']);
        return $data;
    }
    public static function all(string $resource,array $query): array
    {
        CatalogFilter::read();
        return array_map(fn($row)=>CatalogModel::map($resource,$row),CatalogRepository::all($resource,CatalogValidator::filters($query)));
    }
    public static function detail(string $resource,int $id): array
    {
        CatalogFilter::read();
        return CatalogModel::map($resource,CatalogRepository::find($resource,$id));
    }
    public static function create(string $resource,array $input): array
    {
        $user=CatalogFilter::administer();
        return CatalogModel::map($resource,CatalogRepository::create($resource,CatalogValidator::validate($resource,$input),(int)$user['userId']));
    }
    public static function update(string $resource,int $id,array $input): array
    {
        $user=CatalogFilter::administer();
        return CatalogModel::map($resource,CatalogRepository::update($resource,$id,CatalogValidator::validate($resource,$input,false),(int)$user['userId']));
    }
    public static function deactivate(string $resource,int $id): array
    {
        $user=CatalogFilter::administer(); CatalogModel::definition($resource);
        if($resource==='grade-levels') throw new ApiException(405,'Không xóa cấp lớp cố định được dùng để so khớp chuyên môn.','METHOD_NOT_ALLOWED');
        $row=CatalogRepository::update($resource,$id,['isActive'=>0],(int)$user['userId']);
        return ['deactivated'=>true,'item'=>CatalogModel::map($resource,$row)];
    }
    public static function settings(): array
    {
        CatalogFilter::administer(); return CatalogRepository::settings();
    }
    public static function setting(string $key,array $input): array
    {
        $user=CatalogFilter::administer();
        return CatalogRepository::setting($key,CatalogValidator::setting($key,$input),(int)$user['userId']);
    }
}
