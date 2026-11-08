<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/academic/scheduling/weekly', '\App\Modules\Academic\Features\Scheduling\SchedulingController::weekly');
$routes->post('api/academic/scheduling/weekly', '\App\Modules\Academic\Features\Scheduling\SchedulingController::createWeekly');
$routes->get('api/academic/scheduling/sessions', '\App\Modules\Academic\Features\Scheduling\SchedulingController::sessions');
$routes->post('api/academic/scheduling/generate-sessions', '\App\Modules\Academic\Features\Scheduling\SchedulingController::generate');
$routes->post('api/academic/scheduling/sessions/(:num)/assign', '\App\Modules\Academic\Features\Scheduling\SchedulingController::assign/$1');
$routes->put('api/academic/scheduling/sessions/(:num)', '\App\Modules\Academic\Features\Scheduling\SchedulingController::update/$1');
