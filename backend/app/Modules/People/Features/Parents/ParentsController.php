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
    public function paymentQr($id) { return $this->respond(fn()=>\App\Modules\Finance\Features\Tuition\TuitionService::paymentQr((int)$id,ParentsService::parentUser())); }
    public function checkout($id) { return $this->respond(function() use ($id) {
        $actor = ParentsService::parentUser();
        $pending = \App\Shared\Store::one("SELECT nha_cung_cap FROM giao_dich_truc_tuyen WHERE hoa_don_id=? AND trang_thai IN ('khoi_tao','cho_xac_nhan') ORDER BY id DESC LIMIT 1", [(int)$id]);
        if (($pending['nha_cung_cap'] ?? null) === 'SEPAY' || ($pending === null && \App\Modules\Finance\Features\Tuition\SePayPayments::status()['ready'])) {
            return \App\Modules\Finance\Features\Tuition\SePayPayments::checkout((int)$id, $actor);
        }
        return \App\Modules\Finance\Features\Tuition\PayOsPayments::checkout((int)$id, $actor);
    }); }
    public function paymentStatus($id) { return $this->respond(function() use ($id) {
        $actor = ParentsService::parentUser();
        $latest = \App\Shared\Store::one("SELECT nha_cung_cap FROM giao_dich_truc_tuyen WHERE hoa_don_id=? AND nha_cung_cap IN ('SEPAY','PAYOS') ORDER BY id DESC LIMIT 1", [(int)$id]);
        if (($latest['nha_cung_cap'] ?? null) === 'SEPAY' || ($latest === null && \App\Modules\Finance\Features\Tuition\SePayPayments::status()['ready'])) {
            return \App\Modules\Finance\Features\Tuition\SePayPayments::paymentStatus((int)$id, $actor);
        }
        return \App\Modules\Finance\Features\Tuition\PayOsPayments::paymentStatus((int)$id, $actor);
    }); }
    public function checkPayment($id) { return $this->respond(function() use ($id) {
        $actor = ParentsService::parentUser();
        $latest = \App\Shared\Store::one("SELECT nha_cung_cap FROM giao_dich_truc_tuyen WHERE hoa_don_id=? AND nha_cung_cap IN ('SEPAY','PAYOS') ORDER BY id DESC LIMIT 1", [(int)$id]);
        if (($latest['nha_cung_cap'] ?? null) === 'SEPAY') {
            return \App\Modules\Finance\Features\Tuition\SePayPayments::paymentStatus((int)$id, $actor);
        }
        return \App\Modules\Finance\Features\Tuition\PayOsPayments::checkInvoice((int)$id, $actor);
    }); }
    public function createEnrollment() { return $this->respond(fn()=>ParentsService::createEnrollment($this->input()),201); }
    public function createLeaveRequest() { return $this->respond(fn()=>ParentsService::createLeaveRequest($this->input()),201); }
    public function createOnlinePayment() { return $this->respond(fn()=>ParentsService::createOnlinePayment($this->input()),201); }
}
