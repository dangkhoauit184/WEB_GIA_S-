<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->post('api/academic/scheduling/operations-sessions','\App\Modules\Academic\Features\Scheduling\SchedulingController::operationCreate');
$routes->put('api/academic/scheduling/operations-sessions/(:num)','\App\Modules\Academic\Features\Scheduling\SchedulingController::operationUpdate/$1');
$routes->post('api/academic/scheduling/admin-sessions','\App\Modules\Academic\Features\Scheduling\SchedulingController::adminCreate');
$routes->put('api/academic/scheduling/admin-sessions/(:num)','\App\Modules\Academic\Features\Scheduling\SchedulingController::adminUpdate/$1');
$routes->get('api/academic/scheduling/weekly', '\App\Modules\Academic\Features\Scheduling\SchedulingController::weekly');
$routes->post('api/academic/scheduling/weekly', '\App\Modules\Academic\Features\Scheduling\SchedulingController::createWeekly');
$routes->get('api/academic/scheduling/sessions', '\App\Modules\Academic\Features\Scheduling\SchedulingController::sessions');
$routes->post('api/academic/scheduling/generate-sessions', '\App\Modules\Academic\Features\Scheduling\SchedulingController::generate');
$routes->post('api/academic/scheduling/sessions/(:num)/assign', '\App\Modules\Academic\Features\Scheduling\SchedulingController::assign/$1');
$routes->put('api/academic/scheduling/sessions/(:num)', '\App\Modules\Academic\Features\Scheduling\SchedulingController::update/$1');
$routes->delete('api/academic/scheduling/sessions/(:num)', '\App\Modules\Academic\Features\Scheduling\SchedulingController::delete/$1');
