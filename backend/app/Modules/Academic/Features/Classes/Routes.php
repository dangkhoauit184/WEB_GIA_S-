<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/academic/classes/registrations','\App\Modules\Academic\Features\Classes\ClassesController::registrations');
$routes->post('api/academic/classes/registrations/(:num)/assign','\App\Modules\Academic\Features\Classes\ClassesController::assignRegistration/$1');
$routes->get('api/academic/classes', '\App\Modules\Academic\Features\Classes\ClassesController::index');
$routes->post('api/academic/classes', '\App\Modules\Academic\Features\Classes\ClassesController::create');
$routes->get('api/academic/classes/(:num)', '\App\Modules\Academic\Features\Classes\ClassesController::show/$1');
$routes->put('api/academic/classes/(:num)', '\App\Modules\Academic\Features\Classes\ClassesController::update/$1');
$routes->get('api/academic/classes/(:num)/enrollments', '\App\Modules\Academic\Features\Classes\ClassesController::enrollments/$1');
$routes->post('api/academic/classes/(:num)/enrollments', '\App\Modules\Academic\Features\Classes\ClassesController::enroll/$1');
$routes->put('api/academic/classes/(:num)/enrollments/(:num)', '\App\Modules\Academic\Features\Classes\ClassesController::updateEnrollment/$1/$2');
