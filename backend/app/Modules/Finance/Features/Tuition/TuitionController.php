<?php
namespace App\Modules\Finance\Features\Tuition;

final class TuitionController extends \App\Shared\ApiController
{
    public function uploadQr() { return $this->respond(function() {
        $actor=\App\Modules\Administration\Features\Permissions\PermissionsFilter::administer();
        $file=$this->request->getFile('qrImage');
        if (!$file) throw new \App\Shared\ApiException(400,'Hãy chọn ảnh mã QR ngân hàng.');
        $result=PaymentQrImages::upload($file);
        \App\Shared\Store::recordAudit((int)$actor['userId'],'them','cau_hinh',null,null,['action'=>'bank_qr_uploaded','imageHash'=>$result['imageHash'],'bankBin'=>$result['bankBin'],'accountNo'=>$result['accountNo']]);
        return $result;
    },201); }
    public function qrPreview($id) { return $this->respond(function() {
        \App\Modules\Administration\Features\Permissions\PermissionsFilter::administer();
        return ['imageUrl'=>PaymentQrImages::preview($id)];
    }); }
    public function payosStatus() { return $this->respond(function() { \App\Modules\Administration\Features\Permissions\PermissionsFilter::administer(); return PayOsPayments::status(); }); }
    public function payosConnect() { return $this->respond(fn()=>PayOsPayments::connect()); }
    public function payosWebhook() { return $this->respond(fn()=>PayOsPayments::webhook($this->input())); }
    public function payosCheck($id) { return $this->respond(function() { \App\Modules\Administration\Features\Permissions\PermissionsFilter::administer(); return PayOsPayments::reconcile((int)$id); }); }
    public function payosCancel($id) { return $this->respond(fn()=>PayOsPayments::cancel((int)$id,\App\Modules\Administration\Features\Permissions\PermissionsFilter::administer())); }
    public function sepayStatus() { return $this->respond(function() { \App\Modules\Administration\Features\Permissions\PermissionsFilter::administer(); return SePayPayments::status(); }); }
    public function sepayWebhook() { return $this->respond(fn()=>SePayPayments::webhook($this->input(), (string)$this->request->getHeaderLine('Authorization'))); }
    public function sepayCancel($id) { return $this->respond(fn()=>SePayPayments::cancel((int)$id,\App\Modules\Administration\Features\Permissions\PermissionsFilter::administer())); }
    public function banks() { return $this->respond(fn()=>TuitionService::banks()); }
    public function saveBank($id=null) { return $this->respond(fn()=>TuitionService::saveBank($this->input(),$id)); }
    public function defaultBank($id) { return $this->respond(fn()=>TuitionService::defaultBank($id)); }
    public function invoices() { return $this->respond(fn()=>TuitionService::invoices()); }
    public function invoice($id) { return $this->respond(fn()=>TuitionService::invoice((int)$id)); }
    public function createInvoice() { return $this->respond(fn()=>TuitionService::createInvoice($this->input()),201); }
    public function publish($id) { return $this->respond(fn()=>TuitionService::publish((int)$id,$this->input())); }
    public function receive($id) { return $this->respond(fn()=>TuitionService::receive((int)$id,$this->input())); }
    public function transactions() { return $this->respond(fn()=>\App\Shared\ApiModel::map(TuitionService::transactions())); }
    public function confirm($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(TuitionService::confirm((int)$id))); }
}
