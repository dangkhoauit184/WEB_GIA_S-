<?php
namespace App\Modules\Finance\Features\Payroll;

use App\Shared\Input;

final class PayrollValidator
{
    public static function reason(array $body): string
    {
        if(array_diff(array_keys($body),['lyDo'])) Input::fail('fields');
        $reason=Input::validate($body,['lyDo'=>['string',true]])['lyDo'];
        if(mb_strlen($reason)>255) Input::fail('lyDo');
        return $reason;
    }
    public static function rate(array $body): int
    {
        if(array_diff(array_keys($body),['donGiaGio'])) Input::fail('fields');
        $value=Input::validate($body,['donGiaGio'=>['integer',true]])['donGiaGio'];
        if($value>999999999999) Input::fail('donGiaGio');
        return $value;
    }
}
