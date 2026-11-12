<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/academic/leave-requests/personal','\App\Modules\Academic\Features\LeaveRequests\LeaveRequestsController::personal');
$routes->get('api/academic/leave-requests/class-overview','\App\Modules\Academic\Features\LeaveRequests\LeaveRequestsController::classOverview');
$routes->post('api/academic/leave-requests/personal/(:num)/decide','\App\Modules\Academic\Features\LeaveRequests\LeaveRequestsController::decidePersonal/$1');
$classLeave='\App\Modules\Academic\Features\LeaveRequests\LeaveRequestsController::';
$routes->get('api/academic/leave-requests/classes',$classLeave.'classRequests');
$routes->post('api/academic/leave-requests/classes/(:num)/decision',$classLeave.'decideClass/$1');
