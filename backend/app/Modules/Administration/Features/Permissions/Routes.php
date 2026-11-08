<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */
$permissions='api/administration/permissions';
$controller='\App\Modules\Administration\Features\Permissions\PermissionsController::';
$routes->get($permissions.'/groups',$controller.'groups');
$routes->get($permissions.'/functions',$controller.'functions');
$routes->get($permissions.'/groups/(:num)/permissions',$controller.'matrix/$1');
$routes->put($permissions.'/groups/(:num)/permissions',$controller.'updateMatrix/$1');
$routes->put($permissions.'/groups/(:num)',$controller.'describe/$1');
$routes->get($permissions.'/accounts',$controller.'accounts');
$routes->put($permissions.'/accounts/(:num)/group',$controller.'assign/$1');
