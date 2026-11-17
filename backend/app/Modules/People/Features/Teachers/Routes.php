<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->post('api/people/teachers/me/availability', '\App\Modules\People\Features\Teachers\TeachersController::addAvailability');
$routes->put('api/people/teachers/me/availability/(:num)', '\App\Modules\People\Features\Teachers\TeachersController::editAvailability/$1');
$routes->delete('api/people/teachers/me/availability/(:num)', '\App\Modules\People\Features\Teachers\TeachersController::deleteAvailability/$1');
$routes->put('api/people/teachers/me/profile', '\\App\\Modules\\People\\Features\\Teachers\\TeachersController::profile');
$routes->get('api/people/teachers/me/opportunities', '\\App\\Modules\\People\\Features\\Teachers\\TeachersController::opportunities');
$routes->post('api/people/teachers/me/opportunities/(:num)/apply', '\\App\\Modules\\People\\Features\\Teachers\\TeachersController::apply/$1');
$routes->get('api/people/teachers', '\App\Modules\People\Features\Teachers\TeachersController::index');
$routes->post('api/people/teachers', '\App\Modules\People\Features\Teachers\TeachersController::create');
$routes->get('api/people/teachers/match', '\App\Modules\People\Features\Teachers\TeachersController::match');
$routes->get('api/people/teachers/(:num)', '\App\Modules\People\Features\Teachers\TeachersController::show/$1');
$routes->put('api/people/teachers/(:num)', '\App\Modules\People\Features\Teachers\TeachersController::update/$1');
