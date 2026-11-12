<?php
namespace App\Modules\Academic\Features\Admissions;

final class AdmissionsController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>AdmissionsService::requests($this->request->getGet())); }
    public function show($id) { return $this->respond(fn()=>AdmissionsService::detail((int)$id)); }
    public function lookups() { return $this->respond(fn()=>\App\Shared\ApiModel::map(AdmissionsService::lookups($this->request->getGet()))); }
    public function create() { return $this->respond(fn()=>AdmissionsService::save($this->input()), 201); }
    public function update($id) { return $this->respond(fn()=>AdmissionsService::save($this->input(), (int)$id)); }
    public function receive($id) { return $this->respond(fn()=>AdmissionsService::receive((int)$id, $this->input())); }
    public function candidates($id) { return $this->respond(fn()=>AdmissionsService::candidates((int)$id)); }
    public function propose($id) { return $this->respond(fn()=>AdmissionsService::propose((int)$id, $this->input()), 201); }
    public function proposals() { return $this->respond(fn()=>AdmissionsService::proposals($this->request->getGet())); }
    public function respondProposal($id) { return $this->respond(fn()=>AdmissionsService::respond((int)$id, $this->input())); }
    public function registrations() { return $this->respond(fn()=>AdmissionsService::registrations($this->request->getGet())); }
}
