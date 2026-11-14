<?php
namespace App\Modules\People\Features\Parents;

final class ParentsController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>ParentsService::all($this->request->getGet())); }
    public function show($id) { return $this->respond(fn()=>ParentsService::detail((int)$id)); }
    public function create() { return $this->respond(fn()=>ParentsService::create($this->input()),201); }
    public function update($id) { return $this->respond(fn()=>ParentsService::update((int)$id,$this->input())); }
    public function publicLanding() { return $this->respond(fn()=>ParentsService::publicLanding()); }
    public function createPublicConsultation() { return $this->respond(fn()=>ParentsService::createPublicConsultation($this->input()),201); }
    public function portal() { return $this->respond(fn()=>ParentsService::portal()); }
    public function createLeaveRequest() { return $this->respond(fn()=>ParentsService::createLeaveRequest($this->input()),201); }
    public function createOnlinePayment() { return $this->respond(fn()=>ParentsService::createOnlinePayment($this->input()),201); }
}
