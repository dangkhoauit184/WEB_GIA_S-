<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->post('api/administration/auth/login', '\App\Modules\Administration\Features\Auth\AuthController::login');
$routes->get('api/administration/auth/google/config', '\App\Modules\Administration\Features\Auth\AuthController::googleConfig');
$routes->post('api/administration/auth/google', '\App\Modules\Administration\Features\Auth\AuthController::googleLogin');
$routes->post('api/administration/auth/register', '\App\Modules\Administration\Features\Auth\AuthController::register');
$routes->post('api/administration/auth/forgot-password/send-otp', '\App\Modules\Administration\Features\Auth\AuthController::sendOtp');
$routes->post('api/administration/auth/forgot-password/reset', '\App\Modules\Administration\Features\Auth\AuthController::resetPassword');
$routes->get('api/administration/auth/me', '\App\Modules\Administration\Features\Auth\AuthController::me');
$routes->put('api/administration/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/administration/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/administration/auth/logout', '\App\Modules\Administration\Features\Auth\AuthController::logout');

$routes->post('api/auth/login', '\App\Modules\Administration\Features\Auth\AuthController::login');
$routes->get('api/auth/google/config', '\App\Modules\Administration\Features\Auth\AuthController::googleConfig');
$routes->post('api/auth/google', '\App\Modules\Administration\Features\Auth\AuthController::googleLogin');
$routes->post('api/auth/register', '\App\Modules\Administration\Features\Auth\AuthController::register');
$routes->post('api/auth/forgot-password/send-otp', '\App\Modules\Administration\Features\Auth\AuthController::sendOtp');
$routes->post('api/auth/forgot-password/reset', '\App\Modules\Administration\Features\Auth\AuthController::resetPassword');
$routes->get('api/auth/me', '\App\Modules\Administration\Features\Auth\AuthController::me');
$routes->get('api/auth/me/profile', '\App\Modules\Administration\Features\Auth\AuthController::profile');
$routes->put('api/auth/me/profile', '\App\Modules\Administration\Features\Auth\AuthController::updateProfile');
$routes->get('api/administration/auth/me/profile', '\App\Modules\Administration\Features\Auth\AuthController::profile');
$routes->put('api/administration/auth/me/profile', '\App\Modules\Administration\Features\Auth\AuthController::updateProfile');
$routes->put('api/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/auth/logout', '\App\Modules\Administration\Features\Auth\AuthController::logout');
