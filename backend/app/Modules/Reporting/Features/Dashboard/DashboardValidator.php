<?php
namespace App\Modules\Reporting\Features\Dashboard;

final class DashboardValidator
{
    public static function month(array $query): \DateTimeImmutable
    {
        $zone=new \DateTimeZone('Asia/Ho_Chi_Minh');
        $value=$query['month']??(new \DateTimeImmutable('now',$zone))->format('Y-m');
        if(!is_string($value) || !preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])$/D',$value)) \App\Shared\Input::fail('month');
        return new \DateTimeImmutable($value.'-01',new \DateTimeZone('Asia/Ho_Chi_Minh'));
    }
}
