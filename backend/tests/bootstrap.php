<?php
require dirname(__DIR__) . '/app/Config/EduCenterEnv.php';
define('FCPATH', dirname(__DIR__) . '/public/');
define('ENVIRONMENT', 'production');
require dirname(__DIR__) . '/app/Config/Paths.php';
$paths=new \Config\Paths();
require $paths->systemDirectory.'/Boot.php';
\CodeIgniter\Boot::bootConsole($paths);

$assertions=0;
function check(bool $condition,string $label): void
{
    global $assertions;
    if(!$condition) throw new \RuntimeException('FAIL: '.$label);
    $assertions++;
}
function rejects(callable $work,string $label): void
{
    try { $work(); } catch(\App\Shared\ApiException $error) {
        check($error->status===400,$label); return;
    }
    throw new \RuntimeException('FAIL: '.$label);
}
function http(string $path,string $method='GET',?array $data=null,?string $token=null,?string $raw=null): array
{
    global $baseUrl;
    $headers="Content-Type: application/json\r\n".($token?"Authorization: Bearer $token\r\n":'');
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,
        'content'=>$raw??($data!==null?json_encode($data,JSON_THROW_ON_ERROR):''),
        'ignore_errors'=>true,'timeout'=>20]]);
    $text=file_get_contents($baseUrl.$path,false,$context);
    if($text===false) throw new \RuntimeException('HTTP server unavailable');
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);
    $body=json_decode($text,true);
    return [(int)$match[1],$body,$text];
}
function startServer(): array
{
    global $baseUrl;
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if(!$socket) throw new \RuntimeException($error);
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $baseUrl='http://'.$address;
    $log=WRITEPATH.'logs/test-http.log';
    $process=proc_open([PHP_BINARY,'-S',$address,'-t',FCPATH,SYSTEMPATH.'rewrite.php'],
        [0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,ROOTPATH);
    if(!is_resource($process)) throw new \RuntimeException('Cannot start HTTP server');
    fclose($pipes[0]);
    $ready=false;
    for($i=0;$i<50;$i++) {
        $probe=@stream_socket_client('tcp://'.$address,$errno,$error,.1);
        if($probe) { fclose($probe); $ready=true; break; }
        usleep(100000);
    }
    if(!$ready) { proc_terminate($process); throw new \RuntimeException('HTTP server failed to start'); }
    return [$process,$log];
}
function requestOk(string $path,string $method='GET',?array $data=null,?string $token=null,int $expected=200): mixed
{
    [$status,$body]=http($path,$method,$data,$token);
    check($status===$expected && ($body['success']??false),"$method $path expected $expected got $status: ".json_encode($body,JSON_UNESCAPED_UNICODE));
    return $body['data']??$body;
}
