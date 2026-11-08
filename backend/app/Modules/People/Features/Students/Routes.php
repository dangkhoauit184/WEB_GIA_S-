<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/people/students', '\App\Modules\People\Features\Students\StudentsController::index');
$routes->post('api/people/students', '\App\Modules\People\Features\Students\StudentsController::create');
$routes->get('api/people/students/(:num)', '\App\Modules\People\Features\Students\StudentsController::show/$1');
$routes->put('api/people/students/(:num)', '\App\Modules\People\Features\Students\StudentsController::update/$1');
$routes->delete('api/people/students/(:num)', '\App\Modules\People\Features\Students\StudentsController::delete/$1');
