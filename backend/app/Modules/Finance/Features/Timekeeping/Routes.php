<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$corrections='\App\Modules\Finance\Features\Timekeeping\TimekeepingController::';
$routes->get('api/finance/timekeeping/corrections',$corrections.'corrections');
$routes->post('api/finance/timekeeping/corrections/(:num)/decision',$corrections.'decide/$1');
