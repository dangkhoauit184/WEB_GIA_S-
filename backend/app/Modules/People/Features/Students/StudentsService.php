<?php
namespace App\Modules\People\Features\Students;

use App\Shared\ApiException;

final class StudentsService
{
    public static function all(array $filters): array
    {
        $user=StudentsFilter::check();
        return array_map(fn($row)=>StudentsModel::map($row), StudentsRepository::all($filters,$user));
    }
    public static function detail(int $id): array
    {
        $user=StudentsFilter::check(); $row=StudentsRepository::all([],$user,$id)[0]??null;
        if (!$row) throw new ApiException(404,'Không tìm thấy học viên.','STUDENT_NOT_FOUND');
        return StudentsModel::map($row,['lopHocs'=>StudentsRepository::classes($id,$user)]);
    }
    public static function create(array $data): array
    {
        StudentsFilter::check('add',true);
        return self::detail(StudentsRepository::create(StudentsValidator::validate($data)));
    }
    public static function update(int $id,array $data): array
    {
        StudentsFilter::check('edit',true); self::detail($id);
        StudentsRepository::update($id,StudentsValidator::validate($data,false));
        return self::detail($id);
    }
    public static function delete(int $id): array
    {
        StudentsFilter::check('delete',true); self::detail($id); StudentsRepository::delete($id);
        return ['deleted'=>true,'id'=>$id];
    }
}
