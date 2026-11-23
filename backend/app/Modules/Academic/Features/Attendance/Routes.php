<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$controller = '\\App\\Modules\\Academic\\Features\\Attendance\\AttendanceController';
$routes->get('api/academic/attendance/workspace', $controller.'::index');
$routes->get('api/academic/attendance/sessions/(:num)', $controller.'::show/$1');
$routes->post('api/academic/attendance/sessions/(:num)/checkin', $controller.'::checkin/$1');
$routes->post('api/academic/attendance/sessions/(:num)/checkout', $controller.'::checkout/$1');
$routes->put('api/academic/attendance/sessions/(:num)/log', $controller.'::save/$1');
$routes->post('api/academic/attendance/sessions/(:num)/material', $controller.'::uploadMaterial/$1');
$routes->delete('api/academic/attendance/sessions/(:num)/material', $controller.'::removeMaterial/$1');
$routes->get('api/academic/attendance/sessions/(:num)/material/download', $controller.'::downloadMaterial/$1');
$routes->post('api/academic/attendance/requests', $controller.'::request');
