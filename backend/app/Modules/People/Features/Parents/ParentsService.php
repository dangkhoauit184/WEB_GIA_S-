<?php
namespace App\Modules\People\Features\Parents;

use App\Shared\{Access,ApiException};

final class ParentsService
{
    private static function reader(): array
    {
        $user=ParentsFilter::check();
        if (!Access::staff($user) && $user['role']!=='PHU_HUYNH') Access::forbidden();
        return $user;
    }
    public static function all(array $filters): array
    {
        return array_map(fn($row)=>ParentsModel::map($row),ParentsRepository::all($filters,self::reader()));
    }
    public static function detail(int $id): array
    {
        $row=ParentsRepository::all([],self::reader(),$id)[0]??null;
        if (!$row) throw new ApiException(404,'Không tìm thấy phụ huynh.','PARENT_NOT_FOUND');
        $children=ParentsRepository::children($id,self::reader());
        return ParentsModel::map($row,['hocViens'=>array_map([ParentsModel::class,'child'],$children)]);
    }
    public static function create(array $data): array
    {
        $actor=ParentsFilter::check('add',true);
        if ($actor['role']!=='ADMIN' && (isset($data['username']) || isset($data['password']))) Access::forbidden();
        return self::detail(ParentsRepository::create(ParentsValidator::validate($data),(int)$actor['userId']));
    }
    public static function update(int $id,array $data): array
    {
        ParentsFilter::check('edit',true); self::detail($id);
        ParentsRepository::update($id,ParentsValidator::validate($data,false));
        return self::detail($id);
    }
}
