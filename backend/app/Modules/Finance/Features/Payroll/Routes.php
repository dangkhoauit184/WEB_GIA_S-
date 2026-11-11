<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$payroll='\App\Modules\Finance\Features\Payroll\PayrollController::';
$routes->get('api/finance/payroll',$payroll.'index');
$routes->get('api/finance/payroll/(:num)/sessions',$payroll.'sessions/$1');
$routes->post('api/finance/payroll/close',$payroll.'close');
$routes->post('api/finance/payroll/(:num)/pay',$payroll.'pay/$1');
$routes->post('api/finance/payroll/(:num)/unlock',$payroll.'unlock/$1');
$routes->put('api/finance/payroll/rates/(:num)',$payroll.'rate/$1');
