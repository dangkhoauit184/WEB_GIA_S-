<?php
namespace App\Modules\Academic\Features\LeaveRequests;

use App\Shared\Input;

final class LeaveRequestsValidator
{
    public static function decision(array $body): array
    {
        if(array_diff(array_keys($body),['trangThai','ghiChu'])) Input::fail('fields');
        $data=Input::validate($body,['trangThai'=>[['da_duyet','tu_choi'],true],'ghiChu'=>['string',false]]);
        if(isset($data['ghiChu']) && mb_strlen($data['ghiChu'])>255) Input::fail('ghiChu');
        return $data;
    }
}
