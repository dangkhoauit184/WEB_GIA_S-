<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/finance/tuition/invoices','\App\Modules\Finance\Features\Tuition\TuitionController::invoices');
$routes->post('api/finance/tuition/invoices','\App\Modules\Finance\Features\Tuition\TuitionController::createInvoice');
$routes->get('api/finance/tuition/invoices/(:num)','\App\Modules\Finance\Features\Tuition\TuitionController::invoice/$1');
$routes->post('api/finance/tuition/invoices/(:num)/publish','\App\Modules\Finance\Features\Tuition\TuitionController::publish/$1');
$routes->post('api/finance/tuition/invoices/(:num)/receipts','\App\Modules\Finance\Features\Tuition\TuitionController::receive/$1');
$routes->get('api/finance/tuition/transactions','\App\Modules\Finance\Features\Tuition\TuitionController::transactions');
$routes->post('api/finance/tuition/transactions/(:num)/confirm','\App\Modules\Finance\Features\Tuition\TuitionController::confirm/$1');
