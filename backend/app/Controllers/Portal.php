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
        'admissions.js'=>'modules/academic/features/admissions/admissions.js',
        'admissions.css'=>'modules/academic/features/admissions/admissions.css',
        'admissions.html'=>'modules/academic/features/admissions/admissions.html',
        'academic-affairs.js'=>'modules/academic/features/academic-affairs/academic-affairs.js',
        'academic-affairs.css'=>'modules/academic/features/academic-affairs/academic-affairs.css',
        'academic-affairs.html'=>'modules/academic/features/academic-affairs/academic-affairs.html',
        'students.js'=>'modules/people/features/students/students.js',
        'parents.js'=>'modules/people/features/parents/parents.js',
        'classes.js'=>'modules/academic/features/classes/classes.js',
    ];

    public function index(): string
    {
        return view('App\Modules\Administration\Features\Auth\Views\login');
    }

    public function administration(): string
    {
        return view('App\Modules\Administration\Features\Permissions\Views\administration');
    }

    public function admissions(): string
    {
        return view('App\Modules\Administration\Features\Permissions\Views\administration', ['portal'=>'admissions']);
    }

    public function administrationAsset(string $name)
    {
        $relative=self::ADMIN_ASSETS[$name]??null;
        if($relative===null) return $this->response->setStatusCode(404);
        $path=ROOTPATH.'../frontend/src/'.$relative;
        $type=match(pathinfo($name, PATHINFO_EXTENSION)) {
            'css'=>'text/css', 'html'=>'text/html', default=>'application/javascript',
        };
        return $this->response->setContentType($type,'UTF-8')->setBody(file_get_contents($path));
    }
}
