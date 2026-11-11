<?php
namespace App\Modules\Finance\Features\Timekeeping;

final class TimekeepingController extends \App\Shared\ApiController
{
    public function corrections() { return $this->respond(fn()=>\App\Shared\ApiModel::map(TimekeepingService::corrections())); }
    public function decide($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(TimekeepingService::decide((int)$id,$this->input()))); }
}
