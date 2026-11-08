<?php
namespace App\Modules\People\Features\Students;

final class StudentsController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>StudentsService::all($this->request->getGet())); }
    public function show($id) { return $this->respond(fn()=>StudentsService::detail((int)$id)); }
    public function create() { return $this->respond(fn()=>StudentsService::create($this->input()),201); }
    public function update($id) { return $this->respond(fn()=>StudentsService::update((int)$id,$this->input())); }
    public function delete($id) { return $this->respond(fn()=>StudentsService::delete((int)$id)); }
}
