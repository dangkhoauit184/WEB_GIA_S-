<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$classLeave='\App\Modules\Academic\Features\LeaveRequests\LeaveRequestsController::';
$routes->get('api/academic/leave-requests/classes',$classLeave.'classRequests');
$routes->post('api/academic/leave-requests/classes/(:num)/decision',$classLeave.'decideClass/$1');
