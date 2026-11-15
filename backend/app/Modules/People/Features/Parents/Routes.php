<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/people/parents/public/landing', '\App\Modules\People\Features\Parents\ParentsController::publicLanding');
$routes->post('api/people/parents/public/consultations', '\App\Modules\People\Features\Parents\ParentsController::createPublicConsultation');
$routes->get('api/people/parents/portal', '\App\Modules\People\Features\Parents\ParentsController::portal');
$routes->post('api/people/parents/leave-requests', '\App\Modules\People\Features\Parents\ParentsController::createLeaveRequest');
$routes->post('api/people/parents/payments', '\App\Modules\People\Features\Parents\ParentsController::createOnlinePayment');

$routes->get('api/people/parents', '\App\Modules\People\Features\Parents\ParentsController::index');
$routes->post('api/people/parents', '\App\Modules\People\Features\Parents\ParentsController::create');
$routes->get('api/people/parents/(:num)', '\App\Modules\People\Features\Parents\ParentsController::show/$1');
$routes->put('api/people/parents/(:num)', '\App\Modules\People\Features\Parents\ParentsController::update/$1');
