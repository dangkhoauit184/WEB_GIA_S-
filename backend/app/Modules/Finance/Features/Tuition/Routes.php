<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/finance/tuition/transactions','\App\Modules\Finance\Features\Tuition\TuitionController::transactions');
$routes->post('api/finance/tuition/transactions/(:num)/confirm','\App\Modules\Finance\Features\Tuition\TuitionController::confirm/$1');
