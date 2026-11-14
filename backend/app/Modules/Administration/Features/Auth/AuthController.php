<?php
namespace App\Modules\Administration\Features\Auth;

final class AuthController extends \App\Shared\ApiController
{
    public function login() { return $this->respond(fn()=>AuthService::login(AuthValidator::login($this->input()))); }
    public function register() { return $this->respond(fn()=>AuthService::register(AuthValidator::register($this->input())), 201); }
    public function sendOtp() { return $this->respond(fn()=>AuthService::sendOtp(AuthValidator::sendOtp($this->input()))); }
    public function resetPassword() { return $this->respond(fn()=>AuthService::resetPassword(AuthValidator::resetPassword($this->input()))); }
    public function me() { return $this->respond(fn()=>['user'=>AuthFilter::user()]); }
    public function changePassword() { return $this->respond(fn()=>AuthService::changePassword(AuthValidator::password($this->input()))); }
    public function logout() { return $this->respond(fn()=>AuthService::logout()); }
}
