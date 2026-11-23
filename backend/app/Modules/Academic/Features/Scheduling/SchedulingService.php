<?php
namespace App\Modules\Academic\Features\Scheduling;

use App\Shared\{Access,ApiException,Store};

final class SchedulingService
{
    public static function weekly(array $filters): array
    {
        return array_map(fn($row)=>SchedulingModel::weekly($row),SchedulingRepository::weekly($filters,SchedulingFilter::check()));
    }
    public static function createWeekly(array $input): array
    {
        $user=SchedulingFilter::check('add',true); $data=SchedulingValidator::weekly($input);
        Access::classAllowed($data['lopId'],$user);
        $id=SchedulingRepository::createWeekly($data);
        return SchedulingModel::weekly(SchedulingRepository::weekly([],$user,$id)[0]);
    }
    public static function sessions(array $filters): array
    {
        return array_map(fn($row)=>SchedulingModel::session($row),SchedulingRepository::sessions($filters,SchedulingFilter::check()));
    }
    public static function generate(array $input): array
    {
        $user=SchedulingFilter::check('add',true); $data=SchedulingValidator::generate($input);
        Access::classAllowed($data['lopId'],$user); SchedulingRepository::generate($data);
        return self::sessions($data);
    }
    private static function session(int $id,array $user): array
    {
        $row=SchedulingRepository::sessions([],$user,$id)[0]??null;
        if(!$row) throw new ApiException(404,'Không tìm thấy buổi học.','SESSION_NOT_FOUND');
        return SchedulingModel::session($row);
    }
    public static function assign(int $id,array $input): array
    {
        $user=SchedulingFilter::check('add',true); $session=self::session($id,$user);
        $data=SchedulingValidator::assignment($input);
        if($user['role']==='GIAO_VU' && $data['vaiTro']==='day_chinh') {
            $class=Store::one('SELECT loai_lop FROM lop_hoc WHERE id=?',[$session['lopId']]);
            $person=Store::one("SELECT phan_loai FROM nhan_su_chuyen_mon WHERE user_id=? AND deleted_at IS NULL AND trang_thai='hoat_dong'",[$data['userId']]);
            $types=array_filter(explode(',',(string)($person['phan_loai']??'')));
            if($class && $class['loai_lop']==='nhom' && in_array('gia_su',$types,true) && !in_array('giao_vien',$types,true)) {
                throw new ApiException(400,'Gia sư chỉ được xếp ca thuộc lớp kèm gia sư (1:1 hoặc 1:2), không xếp vào lớp nhóm.');
            }
        }
        $rows=SchedulingRepository::assignment($id,$data,(int)$user['userId']);
        if($user['role']!=='ADMIN') foreach($rows as &$row) unset($row['donGia']);
        return $rows;
    }
    public static function update(int $id,array $input): array
    {
        $user=SchedulingFilter::check('edit',true); $existing=self::session($id,$user);
        if(in_array($input['trangThai']??'', ['huy','doi'],true) && $user['role']!=='ADMIN') \App\Shared\Access::forbidden();
        $data=SchedulingValidator::session($input); SchedulingValidator::session(array_merge($existing,$data));
        SchedulingRepository::update($id,$data,(int)$user['userId']);
        return self::session($id,$user);
    }

    public static function delete(int $id,array $input): array
    {
        if($input!==[]) \App\Shared\Input::fail('fields');
        $actor=SchedulingFilter::check('edit',true);
        $session=self::session($id,$actor);
        return SchedulingRepository::deletePlanned($id,(int)$session['lopId'],$actor);
    }
}
