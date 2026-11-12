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
        return $this->frontendPage('index.html');
    }

    public function login(): string
    {
        return $this->frontendPage('src/modules/administration/features/auth/auth.html');
    }

    public function parent(): string
    {
        return $this->frontendPage('src/modules/people/features/parents/parents.html');
    }

    public function pendingPortal()
    {
        $labels = ['GIAO_VIEN'=>'Giáo viên', 'GIAO_SU'=>'Gia sư', 'TRO_GIANG'=>'Trợ giảng'];
        $role = (string)$this->request->getGet('role');
        if (!isset($labels[$role])) return redirect()->to('/');
        $label = $labels[$role];
        return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cổng đang triển khai · EduCenter</title><link rel="stylesheet" href="/src/shared/styles/common.css"></head><body><main style="max-width:640px;margin:12vh auto;padding:24px"><section class="card"><h1>Cổng '.$label.' đang được tích hợp</h1><p>Màn hình nghiệp vụ của cổng này chưa được hoàn thiện. Vui lòng đăng nhập bằng tài khoản tương ứng khi cổng sẵn sàng.</p><p><a href="/">Về trang giới thiệu</a> · <a href="/login">Đăng nhập</a></p></section></main></body></html>';
    }

    private function frontendPage(string $relative): string
    {
        $path = ROOTPATH.'../frontend/'.$relative;
        if (!is_file($path)) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound($relative);
        return file_get_contents($path) ?: '';
    }

    public function frontendAsset(string ...$segments)
    {
        $relative = implode('/', $segments);
        if ($relative === '' || str_contains($relative, '..') || !preg_match('/\A[a-zA-Z0-9._\/-]+\z/', $relative)) {
            return $this->response->setStatusCode(404);
        }
        $base = realpath(ROOTPATH.'../frontend/src');
        $path = $base === false ? false : realpath($base.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
        if ($base === false || $path === false || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) {
            return $this->response->setStatusCode(404);
        }
        $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'text/css', 'js' => 'application/javascript', 'html' => 'text/html',
            'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
            default => null,
        };
        if ($type === null) return $this->response->setStatusCode(404);
        return $this->response->setContentType($type, 'UTF-8')->setBody(file_get_contents($path));
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
