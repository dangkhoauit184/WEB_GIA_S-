<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/people/parents', '\App\Modules\People\Features\Parents\ParentsController::index');
$routes->post('api/people/parents', '\App\Modules\People\Features\Parents\ParentsController::create');
$routes->get('api/people/parents/(:num)', '\App\Modules\People\Features\Parents\ParentsController::show/$1');
$routes->put('api/people/parents/(:num)', '\App\Modules\People\Features\Parents\ParentsController::update/$1');
