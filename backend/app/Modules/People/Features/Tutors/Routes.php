<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$controller = '\App\Modules\People\Features\Tutors\TutorsController::';

// Cổng Gia sư (/api/people/tutors/me/*) — đặt trước route (:num)
$routes->get('api/people/tutors/me/workspace', $controller.'workspace');
$routes->get('api/people/tutors/me/sessions/(:num)', $controller.'session/$1');
$routes->post('api/people/tutors/me/sessions/(:num)/checkin', $controller.'checkin/$1');
$routes->post('api/people/tutors/me/sessions/(:num)/checkout', $controller.'checkout/$1');
$routes->put('api/people/tutors/me/sessions/(:num)/log', $controller.'saveLog/$1');
$routes->post('api/people/tutors/me/sessions/(:num)/material', $controller.'uploadMaterial/$1');
$routes->delete('api/people/tutors/me/sessions/(:num)/material', $controller.'removeMaterial/$1');
$routes->post('api/people/tutors/me/requests', $controller.'submitRequest');
$routes->post('api/people/tutors/me/availability', $controller.'addTutorAvailability');
$routes->put('api/people/tutors/me/availability/(:num)', $controller.'editTutorAvailability/$1');
$routes->delete('api/people/tutors/me/availability/(:num)', $controller.'deleteTutorAvailability/$1');
$routes->get('api/people/tutors/me/opportunities', $controller.'myOpportunities');
$routes->post('api/people/tutors/me/opportunities/(:num)/apply', $controller.'applyOpportunity/$1');

// Route quản trị hồ sơ Gia sư hiện có
$routes->get('api/people/tutors', $controller.'index');
$routes->post('api/people/tutors', $controller.'create');
$routes->get('api/people/tutors/match', $controller.'match');
$routes->get('api/people/tutors/(:num)', $controller.'show/$1');
$routes->put('api/people/tutors/(:num)', $controller.'update/$1');
