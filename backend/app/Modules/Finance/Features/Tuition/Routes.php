<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->post('api/finance/tuition/qr-images','\App\Modules\Finance\Features\Tuition\TuitionController::uploadQr');
$routes->get('api/finance/tuition/bank-accounts/(:segment)/qr-image','\App\Modules\Finance\Features\Tuition\TuitionController::qrPreview/$1');
$routes->get('api/finance/tuition/payos/status','\App\Modules\Finance\Features\Tuition\TuitionController::payosStatus');
$routes->post('api/finance/tuition/payos/connect','\App\Modules\Finance\Features\Tuition\TuitionController::payosConnect');
$routes->post('api/finance/tuition/payos/webhook','\App\Modules\Finance\Features\Tuition\TuitionController::payosWebhook');
$routes->post('api/finance/tuition/transactions/(:num)/check-payos','\App\Modules\Finance\Features\Tuition\TuitionController::payosCheck/$1');
$routes->post('api/finance/tuition/transactions/(:num)/cancel-payos','\App\Modules\Finance\Features\Tuition\TuitionController::payosCancel/$1');
$routes->get('api/finance/tuition/sepay/status','\App\Modules\Finance\Features\Tuition\TuitionController::sepayStatus');
$routes->post('api/finance/tuition/sepay/webhook','\App\Modules\Finance\Features\Tuition\TuitionController::sepayWebhook');
$routes->post('api/finance/tuition/transactions/(:num)/cancel-sepay','\App\Modules\Finance\Features\Tuition\TuitionController::sepayCancel/$1');
$routes->get('api/finance/tuition/bank-accounts','\App\Modules\Finance\Features\Tuition\TuitionController::banks');
$routes->post('api/finance/tuition/bank-accounts','\App\Modules\Finance\Features\Tuition\TuitionController::saveBank');
$routes->put('api/finance/tuition/bank-accounts/(:segment)','\App\Modules\Finance\Features\Tuition\TuitionController::saveBank/$1');
$routes->post('api/finance/tuition/bank-accounts/(:segment)/default','\App\Modules\Finance\Features\Tuition\TuitionController::defaultBank/$1');
$routes->get('api/finance/tuition/invoices','\App\Modules\Finance\Features\Tuition\TuitionController::invoices');
$routes->post('api/finance/tuition/invoices','\App\Modules\Finance\Features\Tuition\TuitionController::createInvoice');
$routes->get('api/finance/tuition/invoices/(:num)','\App\Modules\Finance\Features\Tuition\TuitionController::invoice/$1');
$routes->post('api/finance/tuition/invoices/(:num)/publish','\App\Modules\Finance\Features\Tuition\TuitionController::publish/$1');
$routes->post('api/finance/tuition/invoices/(:num)/receipts','\App\Modules\Finance\Features\Tuition\TuitionController::receive/$1');
$routes->get('api/finance/tuition/transactions','\App\Modules\Finance\Features\Tuition\TuitionController::transactions');
$routes->post('api/finance/tuition/transactions/(:num)/confirm','\App\Modules\Finance\Features\Tuition\TuitionController::confirm/$1');
