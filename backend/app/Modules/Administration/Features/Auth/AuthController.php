<?php
namespace App\Modules\Administration\Features\Auth;

final class AuthController extends \App\Shared\ApiController
{
    public function login() { return $this->respond(fn()=>AuthService::login(AuthValidator::login($this->input()))); }
    public function me() { return $this->respond(fn()=>['user'=>AuthFilter::user()]); }
    public function changePassword() { return $this->respond(fn()=>AuthService::changePassword(AuthValidator::password($this->input()))); }
    public function logout() { return $this->respond(fn()=>AuthService::logout()); }
}
