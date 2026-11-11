<?php
namespace App\Modules\Administration\Features\Staff;

final class StaffController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>\App\Shared\ApiModel::map(StaffService::all())); }
    public function create() { return $this->respond(fn()=>\App\Shared\ApiModel::map(StaffService::create($this->input())),201); }
    public function profile($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(StaffService::profile((int)$id))); }
    public function updateProfile($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(StaffService::updateProfile((int)$id,$this->input()))); }
}
