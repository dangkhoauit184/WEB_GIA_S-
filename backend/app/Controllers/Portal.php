<?php
namespace App\Controllers;

final class Portal extends \CodeIgniter\Controller
{
    public const ADMIN_ASSETS = [
        'base.css'=>'shared/styles/common.css',
        'administration.css'=>'modules/administration/administration.css',
        'administration.js'=>'modules/administration/administration.module.js',
        'views.js'=>'modules/administration/administration.views.js',
        'tuition.js'=>'modules/finance/features/tuition/tuition.js',
        'payroll.js'=>'modules/finance/features/payroll/payroll.js',
        'timekeeping.js'=>'modules/finance/features/timekeeping/timekeeping.js',
        'leave-requests.js'=>'modules/academic/features/leave-requests/leave-requests.js',
        'staff.js'=>'modules/administration/features/staff/staff.js',
        'schedule.js'=>'modules/academic/features/scheduling/admin-schedule.js',
        'catalog.js'=>'modules/administration/features/catalog/catalog.js',
        'permissions.js'=>'modules/administration/features/permissions/permissions.js',
        'audit.js'=>'modules/administration/features/audit/audit.js',
        'dashboard.js'=>'modules/reporting/features/dashboard/dashboard.js',
    ];

    public function index(): string
    {
        return view('App\Modules\Administration\Features\Auth\Views\login');
    }

    public function administration(): string
    {
        return view('App\Modules\Administration\Features\Permissions\Views\administration');
    }

    public function administrationAsset(string $name)
    {
        $relative=self::ADMIN_ASSETS[$name]??null;
        if($relative===null) return $this->response->setStatusCode(404);
        $path=ROOTPATH.'../frontend/src/'.$relative;
        $type=str_ends_with($name,'.css')?'text/css':'application/javascript';
        return $this->response->setContentType($type,'UTF-8')->setBody(file_get_contents($path));
    }
}
