<?php
namespace App\Modules\Academic\Features\Scheduling;

final class SchedulingController extends \App\Shared\ApiController
{
    public function operationCreate() { return $this->respond(fn()=>AdminSchedule::save($this->input(),null,true),201); }
    public function operationUpdate($id) { return $this->respond(fn()=>AdminSchedule::save($this->input(),(int)$id,true)); }
    public function adminCreate() { return $this->respond(fn()=>AdminSchedule::save($this->input()),201); }
    public function adminUpdate($id) { return $this->respond(fn()=>AdminSchedule::save($this->input(),(int)$id)); }
    public function weekly() { return $this->respond(fn()=>SchedulingService::weekly($this->request->getGet())); }
    public function createWeekly() { return $this->respond(fn()=>SchedulingService::createWeekly($this->input()),201); }
    public function sessions() { return $this->respond(fn()=>SchedulingService::sessions($this->request->getGet())); }
    public function generate() { return $this->respond(fn()=>SchedulingService::generate($this->input()),201); }
    public function assign($id) { return $this->respond(fn()=>SchedulingService::assign((int)$id,$this->input()),201); }
    public function update($id) { return $this->respond(fn()=>SchedulingService::update((int)$id,$this->input())); }
}
