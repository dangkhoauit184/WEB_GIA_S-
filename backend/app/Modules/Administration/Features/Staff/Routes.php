<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$staff='\App\Modules\Administration\Features\Staff\StaffController::';
$routes->get('api/administration/staff',$staff.'index');
$routes->post('api/administration/staff',$staff.'create');
$routes->get('api/administration/staff/(:num)/profile',$staff.'profile/$1');
$routes->put('api/administration/staff/(:num)/profile',$staff.'updateProfile/$1');
