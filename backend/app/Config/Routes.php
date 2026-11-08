<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('/', '\App\Controllers\Portal::index');
$routes->get('api/health', '\App\Controllers\Health::index');
$routes->get('health', '\App\Controllers\Health::index');
$routes->get('api', '\App\Controllers\Health::manifest');
$routes->options('(:any)', static fn()=>service('response')->setStatusCode(204));
$modules=json_decode(file_get_contents(ROOTPATH.'../shared/modules.json'),true,512,JSON_THROW_ON_ERROR);
$ready=['auth','parents','students','teachers','tutors','classes','scheduling','catalog','permissions'];
$pascal=static fn($name)=>str_replace(' ','',ucwords(str_replace('-',' ',$name)));
foreach($modules as $module=>$features) {
    foreach($features as $feature) {
        $routes->get("api/$module/$feature/status",static fn()=>service('response')->setJSON([
            'success'=>true,'module'=>$module,'feature'=>$feature,'status'=>in_array($feature,$ready,true)?'ready':'planned',
        ]));
        require APPPATH.'Modules/'.$pascal($module).'/Features/'.$pascal($feature).'/Routes.php';
    }
}
$routes->set404Override('\App\Controllers\Health::notFound');
