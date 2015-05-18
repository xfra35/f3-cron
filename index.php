<?php
$f3=require('lib/base.php');

$f3->mset(array(
    'AUTOLOAD'=>'tests/',
    'UI'=>'tests/',
    'TEMP'=>'var/tmp/',
    'LOGS'=>'var/log/',
    'DEBUG'=>3,
));
$f3->route('GET /','Tests->run');

$cron=Cron::instance();
$cron->set('test1','Jobs->test1','* * * * *');
$cron->set('test2','Jobs->test2','* * * * *');

$f3->run();
