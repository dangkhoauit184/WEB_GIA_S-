<?php
namespace App\Modules\People\Features\Parents;

final class ParentsController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>ParentsService::all($this->request->getGet())); }
    public function show($id) { return $this->respond(fn()=>ParentsService::detail((int)$id)); }
    public function create() { return $this->respond(fn()=>ParentsService::create($this->input()),201); }
    public function update($id) { return $this->respond(fn()=>ParentsService::update((int)$id,$this->input())); }
}
