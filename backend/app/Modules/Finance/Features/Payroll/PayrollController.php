<?php
namespace App\Modules\Finance\Features\Payroll;

final class PayrollController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>\App\Shared\ApiModel::map(PayrollService::summary($this->request->getGet()))); }
    public function sessions($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(PayrollService::sessions((int)$id,$this->request->getGet()))); }
    public function close() { return $this->respond(fn()=>\App\Shared\ApiModel::map(PayrollService::close($this->input()))); }
    public function pay($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(PayrollService::pay((int)$id,$this->input()))); }
    public function unlock($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(PayrollService::unlock((int)$id,$this->input()))); }
    public function rate($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(PayrollService::rate((int)$id,$this->input()))); }
}
