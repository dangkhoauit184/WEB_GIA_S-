<?php
namespace App\Modules\People\Features\Teachers;

class TeachersController extends \App\Shared\ApiController
{
    protected ?string $type=null;
    public function addAvailability() { return $this->respond(fn()=>TeachersService::saveAvailability($this->input()),201); }
    public function editAvailability($id) { return $this->respond(fn()=>TeachersService::saveAvailability($this->input(),(int)$id)); }
    public function deleteAvailability($id) { return $this->respond(fn()=>TeachersService::deleteAvailability((int)$id)); }
    public function profile() { return $this->respond(fn()=>TeachersService::updateProfile($this->input())); }
    public function opportunities() { return $this->respond(fn()=>TeachersService::opportunities()); }
    public function apply($id) { return $this->respond(fn()=>TeachersService::apply((int)$id),201); }
    public function index() { return $this->respond(fn()=>TeachersService::all($this->request->getGet(),$this->type)); }
    public function show($id) { return $this->respond(fn()=>TeachersService::detail((int)$id,$this->type)); }
    public function match() { return $this->respond(fn()=>TeachersService::match($this->request->getGet(),$this->type)); }
    public function create() { return $this->respond(fn()=>TeachersService::create($this->input(),$this->type??'giao_vien'),201); }
    public function update($id) { return $this->respond(fn()=>TeachersService::update((int)$id,$this->input(),$this->type)); }
}
