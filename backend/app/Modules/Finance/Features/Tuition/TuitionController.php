<?php
namespace App\Modules\Finance\Features\Tuition;

final class TuitionController extends \App\Shared\ApiController
{
    public function transactions() { return $this->respond(fn()=>\App\Shared\ApiModel::map(TuitionService::transactions())); }
    public function confirm($id) { return $this->respond(fn()=>\App\Shared\ApiModel::map(TuitionService::confirm((int)$id))); }
}
