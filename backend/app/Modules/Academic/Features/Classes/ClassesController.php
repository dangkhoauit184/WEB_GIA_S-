<?php
namespace App\Modules\Academic\Features\Classes;

final class ClassesController extends \App\Shared\ApiController
{
    public function registrations() { return $this->respond(fn()=>\App\Shared\ApiModel::map(ClassPlacement::registrations())); }
    public function assignRegistration($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(ClassPlacement::assign((int)$id,$this->input()))); }
    public function index() { return $this->respond(fn()=>ClassesService::all($this->request->getGet())); }
    public function show($id) { return $this->respond(fn()=>ClassesService::detail((int)$id)); }
    public function create() { return $this->respond(fn()=>ClassesService::create($this->input()),201); }
    public function update($id) { return $this->respond(fn()=>ClassesService::update((int)$id,$this->input())); }
    public function enrollments($id) { return $this->respond(fn()=>ClassesService::enrollments((int)$id)); }
    public function enroll($id) { return $this->respond(fn()=>ClassesService::enroll((int)$id,$this->input()),201); }
    public function updateEnrollment($id,$enrollmentId) { return $this->respond(fn()=>ClassesService::updateEnrollment((int)$id,(int)$enrollmentId,$this->input())); }
}
