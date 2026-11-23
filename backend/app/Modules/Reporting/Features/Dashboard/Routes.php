<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/reporting/dashboard', '\App\Modules\Reporting\Features\Dashboard\DashboardController::index');
$routes->get('api/reporting/dashboard/reports/meta', '\App\Modules\Reporting\Features\Dashboard\DashboardController::reportMeta');
$routes->get('api/reporting/dashboard/reports/(:segment)/export', '\App\Modules\Reporting\Features\Dashboard\DashboardController::export/$1');
$routes->get('api/reporting/dashboard/reports/(:segment)', '\App\Modules\Reporting\Features\Dashboard\DashboardController::report/$1');
