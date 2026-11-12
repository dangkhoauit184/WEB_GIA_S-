<?php
namespace App\Modules\Academic\Features\LeaveRequests;

use App\Modules\Administration\Features\Permissions\PermissionsFilter;

final class LeaveRequestsService
{
    public static function personal(): array
    {
        $actor=\App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check();
        return \App\Shared\ApiModel::map(LeaveRequestsRepository::personal($actor));
    }

    public static function decidePersonal(int $id,array $body): array
    {
        $actor=\App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check('VanHanhBuoiHoc','edit');
        if(array_diff(array_keys($body),['trangThai','lyDoTuChoi'])) \App\Shared\Input::fail('fields');
        $data=\App\Shared\Input::validate($body,['trangThai'=>[['da_duyet','tu_choi'],true],'lyDoTuChoi'=>['string',false]]);
        $data['lyDoTuChoi']=$data['lyDoTuChoi']?:null;
        if(mb_strlen($data['lyDoTuChoi']??'')>255 || $data['trangThai']==='tu_choi' && !$data['lyDoTuChoi'] || $data['trangThai']==='da_duyet' && $data['lyDoTuChoi']) \App\Shared\Input::fail('lyDoTuChoi');
        return \App\Shared\ApiModel::map(LeaveRequestsRepository::decidePersonal($id,$data,$actor));
    }

    public static function classRequests(): array
    {
        PermissionsFilter::administer();
        return LeaveRequestsRepository::classRequests();
    }
    public static function decideClass(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        return LeaveRequestsRepository::decideClass($id,LeaveRequestsValidator::decision($body),$actor);
    }
}
