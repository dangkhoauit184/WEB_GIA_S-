<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/people/teachers', '\App\Modules\People\Features\Teachers\TeachersController::index');
$routes->post('api/people/teachers', '\App\Modules\People\Features\Teachers\TeachersController::create');
$routes->get('api/people/teachers/match', '\App\Modules\People\Features\Teachers\TeachersController::match');
$routes->get('api/people/teachers/(:num)', '\App\Modules\People\Features\Teachers\TeachersController::show/$1');
$routes->put('api/people/teachers/(:num)', '\App\Modules\People\Features\Teachers\TeachersController::update/$1');
