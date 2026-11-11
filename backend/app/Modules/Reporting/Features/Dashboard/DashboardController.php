<?php
namespace App\Modules\Reporting\Features\Dashboard;

final class DashboardController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>DashboardService::summary($this->request->getGet())); }
}
