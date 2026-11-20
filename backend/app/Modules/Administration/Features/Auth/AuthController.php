<?php
namespace App\Modules\Administration\Features\Auth;

final class AuthController extends \App\Shared\ApiController
{
    public function login()
    {
        return $this->respond(function () {
            $result = AuthService::login(AuthValidator::login($this->input()));
            $this->response->setCookie([
                'name'=>'educenter_session', 'value'=>$result['token'], 'expire'=>0,
                'path'=>'/', 'secure'=>$this->request->isSecure(), 'httponly'=>true, 'samesite'=>'Lax',
            ]);
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
            $this->response->deleteCookie('educenter_session');
            return $result;
        });
    }
    public function logout()
    {
        $this->response->deleteCookie('educenter_session');
        return $this->respond(fn()=>AuthService::logout());
    }
}
