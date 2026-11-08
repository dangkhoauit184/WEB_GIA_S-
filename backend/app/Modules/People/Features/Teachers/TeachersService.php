<?php
namespace App\Modules\People\Features\Teachers;

use App\Shared\ApiException;

final class TeachersService
{
    public static function all(array $filters,?string $type=null): array
    {
        if($type) $filters['phanLoai']=$type;
        return array_map(fn($row)=>TeachersModel::map($row),TeachersRepository::all($filters,TeachersFilter::check()));
    }
    public static function detail(int $id,?string $type=null): array
    {
        $row=TeachersRepository::all($type?['phanLoai'=>$type]:[],TeachersFilter::check(),$id)[0]??null;
        if (!$row) throw new ApiException(404,'Không tìm thấy người dạy.','TEACHER_NOT_FOUND');
        return TeachersModel::map($row,TeachersRepository::extras($id));
    }
    public static function match(array $filters,?string $type=null): array
    {
        TeachersFilter::check('view',true);
        return array_map(fn($row)=>TeachersModel::map($row),TeachersRepository::match(TeachersValidator::match($filters),$type));
    }
    public static function create(array $input,string $type='giao_vien'): array
    {
        TeachersFilter::check('add',true);
        return self::detail(TeachersRepository::create(TeachersValidator::validate($input,true,$type)));
    }
    public static function update(int $id,array $input,?string $type=null): array
    {
        TeachersFilter::check('edit',true); $row=self::detail($id,$type);
        TeachersRepository::update($id,(int)$row['userId'],TeachersValidator::validate($input,false));
        return self::detail($id);
    }
}
