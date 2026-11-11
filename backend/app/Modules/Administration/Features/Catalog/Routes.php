<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$catalog='api/administration/catalog';
$controller='\App\Modules\Administration\Features\Catalog\CatalogController::';
$routes->get($catalog,$controller.'index');
$routes->get($catalog.'/rooms',$controller.'rooms');
$routes->post($catalog.'/rooms',$controller.'createRoom');
$routes->get($catalog.'/settings',$controller.'settings');
$routes->put($catalog.'/settings/(:segment)',$controller.'setting/$1');
$resource='(subjects|grade-levels|regions|campuses|courses)';
$routes->get($catalog.'/'.$resource,$controller.'list/$1');
$routes->post($catalog.'/'.$resource,$controller.'create/$1');
$routes->get($catalog.'/'.$resource.'/(:num)',$controller.'show/$1/$2');
$routes->put($catalog.'/'.$resource.'/(:num)',$controller.'update/$1/$2');
$routes->delete($catalog.'/'.$resource.'/(:num)',$controller.'delete/$1/$2');
