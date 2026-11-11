<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$controller = '\App\Modules\Academic\Features\Admissions\AdmissionsController::';
$routes->get('api/academic/admissions/requests', $controller.'index');
$routes->post('api/academic/admissions/requests', $controller.'create');
$routes->get('api/academic/admissions/requests/(:num)', $controller.'show/$1');
$routes->put('api/academic/admissions/requests/(:num)', $controller.'update/$1');
$routes->post('api/academic/admissions/requests/(:num)/receive', $controller.'receive/$1');
$routes->get('api/academic/admissions/requests/(:num)/candidates', $controller.'candidates/$1');
$routes->post('api/academic/admissions/requests/(:num)/proposals', $controller.'propose/$1');
$routes->get('api/academic/admissions/proposals', $controller.'proposals');
$routes->post('api/academic/admissions/proposals/(:num)/respond', $controller.'respondProposal/$1');
$routes->get('api/academic/admissions/registrations', $controller.'registrations');
$routes->get('api/academic/admissions/lookups', $controller.'lookups');
