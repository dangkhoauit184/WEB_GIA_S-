<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->post('api/people/parents/invoices/(:num)/checkout', '\App\Modules\People\Features\Parents\ParentsController::checkout/$1');
$routes->get('api/people/parents/invoices/(:num)/payment-status', '\App\Modules\People\Features\Parents\ParentsController::paymentStatus/$1');
$routes->post('api/people/parents/invoices/(:num)/check-payment', '\App\Modules\People\Features\Parents\ParentsController::checkPayment/$1');
$routes->get('api/people/parents/invoices/(:num)/payment-qr', '\App\Modules\People\Features\Parents\ParentsController::paymentQr/$1');
$routes->get('api/people/parents/public/landing', '\App\Modules\People\Features\Parents\ParentsController::publicLanding');
$routes->post('api/people/parents/public/consultations', '\App\Modules\People\Features\Parents\ParentsController::createPublicConsultation');
$routes->get('api/people/parents/portal', '\App\Modules\People\Features\Parents\ParentsController::portal');
$routes->post('api/people/parents/enrollments', '\App\Modules\People\Features\Parents\ParentsController::createEnrollment');
$routes->post('api/people/parents/leave-requests', '\App\Modules\People\Features\Parents\ParentsController::createLeaveRequest');
$routes->post('api/people/parents/payments', '\App\Modules\People\Features\Parents\ParentsController::createOnlinePayment');

$routes->get('api/people/parents', '\App\Modules\People\Features\Parents\ParentsController::index');
$routes->post('api/people/parents', '\App\Modules\People\Features\Parents\ParentsController::create');
$routes->get('api/people/parents/(:num)', '\App\Modules\People\Features\Parents\ParentsController::show/$1');
$routes->put('api/people/parents/(:num)', '\App\Modules\People\Features\Parents\ParentsController::update/$1');
