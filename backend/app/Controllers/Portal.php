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

    private function authenticatedCookieUser(): ?array
    {
        $token = $this->request->getCookie('educenter_session');
        if (!is_string($token) || $token === '' || strlen($token) > 16384) return null;
        try {
            return \App\Shared\Access::user();
        } catch (\App\Shared\ApiException) {
            return null;
        }
    }

    private function injectPortalSession(string $html, array $user): string
    {
        // Only expose non-sensitive UI user metadata; the JWT stays exclusively in the HttpOnly cookie.
        $session = json_encode(['user' => $user], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        $script = '<script type="application/json" id="portal-session">'.$session.'</script>';
        return str_replace('<head>', '<head>'.$script, $html);
    }

    public function index(): string
    {
        $html = $this->frontendPage('index.html');
        $user = $this->authenticatedCookieUser();
        return $user ? $this->injectPortalSession($html, $user) : $html;
    }

    public function login()
    {
        $allowStay = $this->request->getGet('loggedOut') === '1'
            || $this->request->getGet('passwordChanged') === '1'
            || $this->request->getGet('switch') === '1';
        if (!$allowStay && $this->authenticatedCookieUser() !== null) {
            return redirect()->to('/app', 303);
        }
        return $this->frontendPage('src/modules/administration/features/auth/auth.html');
    }

    public function administration()
    {
        return redirect()->to('/app', 303);
    }

    public function admissions()
    {
        return redirect()->to('/app', 303);
    }

    public function application()
    {
        try {
            $token = $this->request->getCookie('educenter_session');
            if (!is_string($token) || $token === '' || strlen($token) > 16384) {
                throw new \App\Shared\ApiException(401, 'Vui lòng đăng nhập lại.', 'UNAUTHORIZED');
            }
            $user = \App\Shared\Access::user();
            if (in_array($user['role'], ['ADMIN', 'GIAO_VU'], true)) {
                $html = view('App\Modules\Administration\Features\Permissions\Views\administration', [
                    'portal'=>$user['role'] === 'ADMIN' ? 'admin' : 'admissions',
                ]);
                $scripts = '<script src="/frontend/src/config/app.config.js"></script>'
                    .'<script src="/frontend/src/shared/permissions/access.js"></script>'
                    .'<script src="/frontend/src/shared/services/http.js"></script>'
                    .'<script src="/frontend/src/shared/utils/helpers.js"></script>';
                $html = str_replace('</head>', $scripts.'</head>', $html);
            } elseif ($user['role'] === 'PHU_HUYNH') {
                $html = $this->frontendPage('src/modules/people/features/parents/parents.html');
            } elseif ($user['role'] === 'GIAO_VIEN') {
                $html = $this->frontendPage('src/modules/people/features/teachers/teachers.html');
            } else {
                $html = $this->frontendPage('src/modules/academic/features/attendance/attendance.html');
            }
            return $this->injectPortalSession($html, $user);
        } catch (\App\Shared\ApiException) {
            return redirect()->to('/login', 303)->setCookie([
                'name' => 'educenter_session', 'value' => '', 'expire' => time() - 3600,
                'path' => '/', 'secure' => \App\Shared\Access::isSecureRequest($this->request),
                'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
    }

    private function frontendPage(string $relative): string
    {
        $html = file_get_contents(ROOTPATH.'../frontend/'.$relative);
        $base = '/frontend/'.(dirname($relative) === '.' ? '' : dirname($relative).'/');
        return preg_replace_callback('/\b(src|href)="(?![a-z][a-z0-9+.-]*:|\/|#)([^"]+)"/i',
            static function (array $match) use ($base): string {
                $path = \CodeIgniter\HTTP\URI::removeDotSegments($base.$match[2]);
                $path = match ($path) {
                    '/frontend/index.html'=>'/',
                    '/frontend/src/modules/administration/features/auth/auth.html'=>'/login',
                    default=>$path,
                };
                return $match[1].'="'.$path.'"';
            }, $html);
    }

    public function frontendAsset(string ...$segments)
    {
        $relative = implode('/', $segments);
        $root = realpath(ROOTPATH.'../frontend');
        $path = realpath($root.'/'.$relative);
        $types = ['html'=>'text/html', 'js'=>'application/javascript', 'css'=>'text/css',
            'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'webp'=>'image/webp',
            'svg'=>'image/svg+xml', 'ico'=>'image/x-icon', 'woff'=>'font/woff', 'woff2'=>'font/woff2'];
        $extension = strtolower(pathinfo($path ?: '', PATHINFO_EXTENSION));
        if ($path === false || !str_starts_with($path, $root.DIRECTORY_SEPARATOR) || !is_file($path) || !isset($types[$extension])) {
            return $this->response->setStatusCode(404);
        }
        return $this->response->setContentType($types[$extension])->setBody(file_get_contents($path));
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
