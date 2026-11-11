<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/administration/audit','\App\Modules\Administration\Features\Audit\AuditController::index');
$routes->get('api/administration/audit/(:num)','\App\Modules\Administration\Features\Audit\AuditController::show/$1');
