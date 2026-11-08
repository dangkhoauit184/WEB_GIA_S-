<?php
namespace App\Modules\People\Features\Teachers;

class TeachersController extends \App\Shared\ApiController
{
    protected ?string $type=null;
    public function index() { return $this->respond(fn()=>TeachersService::all($this->request->getGet(),$this->type)); }
    public function show($id) { return $this->respond(fn()=>TeachersService::detail((int)$id,$this->type)); }
    public function match() { return $this->respond(fn()=>TeachersService::match($this->request->getGet(),$this->type)); }
    public function create() { return $this->respond(fn()=>TeachersService::create($this->input(),$this->type??'giao_vien'),201); }
    public function update($id) { return $this->respond(fn()=>TeachersService::update((int)$id,$this->input(),$this->type)); }
}
