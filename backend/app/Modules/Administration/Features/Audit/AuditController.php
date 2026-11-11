<?php
namespace App\Modules\Administration\Features\Audit;

final class AuditController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>AuditService::all($this->request->getGet())); }
    public function show($id) { return $this->respond(fn()=>AuditService::detail((int)$id)); }
}
