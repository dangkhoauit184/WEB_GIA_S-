<?php
namespace App\Modules\Administration\Features\Auth;

final class AuthController extends \App\Shared\ApiController
{
    private function setSessionCookie(array $result): void
    {
        $rememberMe = !empty($result['rememberMe']);
        $expiresIn = (int)($result['expiresIn'] ?? 0);
        $this->response->setCookie([
            'name' => 'educenter_session',
            'value' => $result['token'],
            'expire' => $rememberMe && $expiresIn > 0 ? time() + $expiresIn : 0,
            'path' => '/',
            'secure' => \App\Shared\Access::isSecureRequest($this->request),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearSessionCookie(): void
    {
        $this->response->setCookie([
            'name' => 'educenter_session',
            'value' => '',
            'expire' => time() - 3600,
            'path' => '/',
            'secure' => \App\Shared\Access::isSecureRequest($this->request),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function login()
    {
        return $this->respond(function () {
            $result = AuthService::login(AuthValidator::login($this->input()));
            $this->setSessionCookie($result);
            return $result;
        });
    }
    public function googleConfig()
    {
        return $this->respond(fn()=>AuthService::googleConfig());
    }
    public function googleLogin()
    {
        return $this->respond(function () {
            $result = AuthService::googleLogin(AuthValidator::googleLogin($this->input()));
            $this->setSessionCookie($result);
            return $result;
        });
    }
    public function register() { return $this->respond(fn()=>AuthService::register(AuthValidator::register($this->input())), 201); }
    public function sendOtp() { return $this->respond(fn()=>AuthService::sendOtp(AuthValidator::sendOtp($this->input()))); }
    public function resetPassword() { return $this->respond(fn()=>AuthService::resetPassword(AuthValidator::resetPassword($this->input()))); }
    public function me() { return $this->respond(fn()=>['user'=>AuthFilter::user()]); }
    public function profile() { return $this->respond(fn()=>AuthService::profile()); }
    public function updateProfile() { return $this->respond(fn()=>AuthService::updateProfile($this->input())); }
    public function changePassword()
    {
        return $this->respond(function () {
            $result = AuthService::changePassword(AuthValidator::password($this->input()));
            $this->clearSessionCookie();
            return $result;
        });
    }
    public function logout()
    {
        $this->clearSessionCookie();
        return $this->respond(fn()=>AuthService::logout());
    }
}
