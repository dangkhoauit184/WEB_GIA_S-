<?php
namespace App\Modules\Administration\Features\Staff;

use App\Modules\Administration\Features\Permissions\PermissionsFilter;
use App\Modules\People\Features\Teachers\{TeachersService,TeachersValidator,TeachersRepository};

final class StaffService
{
    public static function all(): array
    {
        PermissionsFilter::administer();
        return StaffRepository::all();
    }
    public static function create(array $body): array
    {
        $actor=PermissionsFilter::administer();
        $id=StaffRepository::create(StaffValidator::create($body),$actor);
        return self::profile($id);
    }
    public static function profile(int $id): array
    {
        PermissionsFilter::administer();
        $person=StaffRepository::find($id);
        return ['user'=>array_intersect_key($person,array_flip(['user_id','hoten','email','sdt'])),
            'professional'=>$person['nhan_su_id']?TeachersService::detail((int)$person['nhan_su_id']):null];
    }
    public static function updateProfile(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        $person=StaffRepository::find($id);
        if(!$person['nhan_su_id']) throw new \App\Shared\ApiException(400,'Nhân sự này không có hồ sơ người dạy.');
        $allowed=['hoTen','email','sdt','gioiThieu','nganHang','soTaiKhoan','chuTaiKhoan','monDays','khuVucIds','lichRanhs'];
        if(array_diff(array_keys($body),$allowed)) \App\Shared\Input::fail('fields');
        TeachersRepository::update((int)$person['nhan_su_id'],$id,TeachersValidator::validate($body,false),(int)$actor['userId']);
        return self::profile($id);
    }
}
