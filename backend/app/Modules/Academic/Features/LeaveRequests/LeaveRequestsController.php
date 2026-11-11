<?php
namespace App\Modules\Academic\Features\LeaveRequests;

final class LeaveRequestsController extends \App\Shared\ApiController
{
    public function personal() { return $this->respond(fn()=>LeaveRequestsService::personal()); }
    public function classOverview() { return $this->respond(fn()=>\App\Shared\ApiModel::map(LeaveRequestsRepository::classRequests(\App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check()))); }
    public function decidePersonal($id) { return $this->respond(fn()=>LeaveRequestsService::decidePersonal((int)$id,$this->input())); }
    public function classRequests() { return $this->respond(fn()=>\App\Shared\ApiModel::map(LeaveRequestsService::classRequests())); }
    public function decideClass($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(LeaveRequestsService::decideClass((int)$id,$this->input()))); }
}
