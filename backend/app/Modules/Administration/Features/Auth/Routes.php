<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->post('api/administration/auth/login', '\App\Modules\Administration\Features\Auth\AuthController::login');
$routes->get('api/administration/auth/me', '\App\Modules\Administration\Features\Auth\AuthController::me');
$routes->put('api/administration/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/administration/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/administration/auth/logout', '\App\Modules\Administration\Features\Auth\AuthController::logout');
$routes->post('api/auth/login', '\App\Modules\Administration\Features\Auth\AuthController::login');
$routes->get('api/auth/me', '\App\Modules\Administration\Features\Auth\AuthController::me');
$routes->put('api/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/auth/change-password', '\App\Modules\Administration\Features\Auth\AuthController::changePassword');
$routes->post('api/auth/logout', '\App\Modules\Administration\Features\Auth\AuthController::logout');
