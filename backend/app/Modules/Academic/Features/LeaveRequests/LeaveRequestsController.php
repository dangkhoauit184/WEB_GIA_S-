<?php
namespace App\Modules\Academic\Features\LeaveRequests;

final class LeaveRequestsController extends \App\Shared\ApiController
{
    public function classRequests() { return $this->respond(fn()=>\App\Shared\ApiModel::map(LeaveRequestsService::classRequests())); }
    public function decideClass($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(LeaveRequestsService::decideClass((int)$id,$this->input()))); }
}
