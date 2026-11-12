<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$controller = '\App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsController::';
$prefix = 'api/academic/academic-affairs';
$routes->get($prefix.'/dashboard', $controller.'dashboard');
$routes->get($prefix.'/rooms', $controller.'rooms');
$routes->get($prefix.'/makeups', $controller.'makeups');
$routes->get($prefix.'/people', $controller.'people');
$routes->get($prefix.'/courses', $controller.'courses');
$routes->post($prefix.'/students', $controller.'createStudent');
$routes->get($prefix.'/students/(:num)/registrations', $controller.'registrations/$1');
$routes->post($prefix.'/students/(:num)/registrations', $controller.'register/$1');
$routes->get($prefix.'/notifications', $controller.'notifications');
$routes->post($prefix.'/notifications/(:num)/read', $controller.'readNotification/$1');
