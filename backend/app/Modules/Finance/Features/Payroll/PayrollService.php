<?php
namespace App\Modules\Finance\Features\Payroll;

use App\Shared\{Input,Store};
use App\Modules\Administration\Features\Permissions\PermissionsFilter;
use App\Modules\Reporting\Features\Dashboard\DashboardValidator;

final class PayrollService
{
    public static function sessions(int $userId,array $query): array
    {
        PermissionsFilter::administer();
        return PayrollRepository::sessions($userId,DashboardValidator::month($query));
    }
    public static function summary(array $query): array
    {
        PermissionsFilter::administer();
        $month=DashboardValidator::month($query);
        return PayrollRepository::summary($month);
    }
    public static function close(array $body): array
    {
        $actor=PermissionsFilter::administer();
        $data=Input::validate($body,['userId'=>['id',true],'month'=>['string',true]]);
        $month=DashboardValidator::month($data);
        Store::call('CALL sp_chot_cong(?,?,?,?)',[$data['userId'],(int)$month->format('m'),(int)$month->format('Y'),$actor['userId']]);
        return PayrollRepository::summary($month);
    }
    public static function pay(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        if(array_diff(array_keys($body),['maGiaoDich'])) Input::fail('fields');
        $data=Input::validate($body,['maGiaoDich'=>['string',false]]);
        if(isset($data['maGiaoDich']) && mb_strlen($data['maGiaoDich'])>60) Input::fail('maGiaoDich');
        return PayrollRepository::pay($id,$data['maGiaoDich']??null,$actor);
    }
    public static function unlock(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        Store::call('CALL sp_mo_khoa_cong(?,?,?)',[$id,$actor['userId'],PayrollValidator::reason($body)]);
        return ['unlocked'=>true];
    }
    public static function rate(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        Store::call('CALL sp_dat_don_gia_gio(?,?,?)',[$id,PayrollValidator::rate($body),$actor['userId']]);
        return ['updated'=>true];
    }
}
