<?php
namespace App\Modules\Finance\Features\Tuition;

final class TuitionController extends \App\Shared\ApiController
{
    public function invoices() { return $this->respond(fn()=>TuitionService::invoices()); }
    public function invoice($id) { return $this->respond(fn()=>TuitionService::invoice((int)$id)); }
    public function createInvoice() { return $this->respond(fn()=>TuitionService::createInvoice($this->input()),201); }
    public function publish($id) { return $this->respond(fn()=>TuitionService::publish((int)$id,$this->input())); }
    public function receive($id) { return $this->respond(fn()=>TuitionService::receive((int)$id,$this->input())); }
    public function transactions() { return $this->respond(fn()=>\App\Shared\ApiModel::map(TuitionService::transactions())); }
    public function confirm($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(TuitionService::confirm((int)$id))); }
}
