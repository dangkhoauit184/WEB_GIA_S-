<?php
namespace App\Modules\Administration\Features\Permissions;

final class PermissionsController extends \App\Shared\ApiController
{
    public function groups() { return $this->respond(fn()=>PermissionsService::groups()); }
    public function functions() { return $this->respond(fn()=>PermissionsService::functions()); }
    public function matrix($id) { return $this->respond(fn()=>PermissionsService::matrix((int)$id)); }
    public function updateMatrix($id) { return $this->respond(fn()=>PermissionsService::updateMatrix((int)$id,$this->input())); }
    public function describe($id) { return $this->respond(fn()=>PermissionsService::describe((int)$id,$this->input())); }
    public function accounts() { return $this->respond(fn()=>PermissionsService::accounts($this->request->getGet())); }
    public function assign($id) { return $this->respond(fn()=>PermissionsService::assign((int)$id,$this->input())); }
}
