<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/people/tutors', '\App\Modules\People\Features\Tutors\TutorsController::index');
$routes->post('api/people/tutors', '\App\Modules\People\Features\Tutors\TutorsController::create');
$routes->get('api/people/tutors/match', '\App\Modules\People\Features\Tutors\TutorsController::match');
$routes->get('api/people/tutors/(:num)', '\App\Modules\People\Features\Tutors\TutorsController::show/$1');
$routes->put('api/people/tutors/(:num)', '\App\Modules\People\Features\Tutors\TutorsController::update/$1');
