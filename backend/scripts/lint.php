<?php
// Lint project PHP only; dependencies are validated by Composer.
$files=[];
foreach(['app','public','tests','scripts'] as $directory) {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/'.$directory,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) if($file->getExtension()==='php') $files[]=$file->getPathname();
}
$files[]=dirname(__DIR__).'/spark';
foreach($files as $file) {
    $process=proc_open([PHP_BINARY,'-l',$file],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0) { fwrite(STDERR,$out.$error);exit(1); }
}
echo 'PASS: '.count($files)." PHP files linted.\n";
