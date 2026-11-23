<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$controller = '\\App\\Modules\\People\\Features\\Assistants\\AssistantsController';
$routes->get('api/people/assistants/workspace', $controller.'::workspace');
$routes->get('api/people/assistants/sessions/(:num)', $controller.'::session/$1');
$routes->post('api/people/assistants/sessions/(:num)/checkin', $controller.'::checkin/$1');
$routes->post('api/people/assistants/sessions/(:num)/checkout', $controller.'::checkout/$1');
$routes->put('api/people/assistants/sessions/(:num)/log', $controller.'::saveLog/$1');
$routes->put('api/people/assistants/sessions/(:num)/attendance', $controller.'::attendance/$1');
$routes->post('api/people/assistants/requests', $controller.'::request');
$routes->post('api/people/assistants/availability', $controller.'::addAvailability');
$routes->put('api/people/assistants/availability/(:num)', $controller.'::editAvailability/$1');
$routes->delete('api/people/assistants/availability/(:num)', $controller.'::deleteAvailability/$1');
