<?php
namespace App\Modules\Academic\Features\Classes;

use App\Shared\{Access,ApiException};

final class ClassesService
{
    public static function all(array $filters): array
    {
        return array_map(fn($row)=>ClassesModel::map($row),ClassesRepository::all($filters,ClassesFilter::check()));
    }
    public static function detail(int $id): array
    {
        $user=ClassesFilter::check(); $row=ClassesRepository::all([],$user,$id)[0]??null;
        if(!$row) throw new ApiException(404,'Không tìm thấy lớp học.','CLASS_NOT_FOUND');
        return ClassesModel::map($row,['hocViens'=>ClassesRepository::enrollments($id,$user),'lichHocs'=>ClassesRepository::schedules($id)]);
    }
    public static function create(array $input): array
    {
        $user=ClassesFilter::check('add',true);
        return self::detail(ClassesRepository::create(ClassesValidator::validate($input),$user));
    }
    public static function update(int $id,array $input): array
    {
        ClassesFilter::check('edit',true); $existing=self::detail($id);
        $data=ClassesValidator::validate($input,false);
        ClassesValidator::validate(array_merge($existing,$data),false);
        ClassesRepository::update($id,$data);
        return self::detail($id);
    }
    public static function enrollments(int $id): array
    {
        $user=ClassesFilter::check(); Access::classAllowed($id,$user);
        return ClassesRepository::enrollments($id,$user);
    }
    public static function enroll(int $id,array $input): array
    {
        $user=ClassesFilter::check('add',true); Access::classAllowed($id,$user);
        ClassesRepository::enroll($id,ClassesValidator::enrollment($input),(int)$user['userId']);
        return self::enrollments($id);
    }
    public static function updateEnrollment(int $id,int $enrollmentId,array $input): array
    {
        $user=ClassesFilter::check('edit',true); Access::classAllowed($id,$user);
        ClassesRepository::updateEnrollment($id,$enrollmentId,ClassesValidator::enrollment($input,false),(int)$user['userId']);
        return self::enrollments($id);
    }
}
