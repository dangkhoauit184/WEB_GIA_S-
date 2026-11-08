<?php
namespace App\Modules\Administration\Features\Catalog;

final class CatalogController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>CatalogService::bootstrap()); }
    public function list($resource) { return $this->respond(fn()=>CatalogService::all($resource,$this->request->getGet())); }
    public function show($resource,$id) { return $this->respond(fn()=>CatalogService::detail($resource,(int)$id)); }
    public function create($resource) { return $this->respond(fn()=>CatalogService::create($resource,$this->input()),201); }
    public function update($resource,$id) { return $this->respond(fn()=>CatalogService::update($resource,(int)$id,$this->input())); }
    public function delete($resource,$id) { return $this->respond(fn()=>CatalogService::deactivate($resource,(int)$id)); }
    public function settings() { return $this->respond(fn()=>CatalogService::settings()); }
    public function setting($key) { return $this->respond(fn()=>CatalogService::setting($key,$this->input())); }
}
