<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->get('api/communication/notifications', '\App\Modules\Communication\Features\Notifications\NotificationsController::index');
$routes->post('api/communication/notifications', '\App\Modules\Communication\Features\Notifications\NotificationsController::create');
$routes->post('api/communication/notifications/read-all', '\App\Modules\Communication\Features\Notifications\NotificationsController::readAll');
$routes->post('api/communication/notifications/(:num)/read', '\App\Modules\Communication\Features\Notifications\NotificationsController::markRead/$1');
$routes->put('api/communication/notifications/(:num)/read', '\App\Modules\Communication\Features\Notifications\NotificationsController::markRead/$1');
