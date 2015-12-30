<?php

include dirname(__DIR__).'/vendor/autoload.php';

$beanstalk = new BeanstalkClient(array_merge(
    $config['message_server'],
    ['persistent' => false]
));
$beanstalk->connect();

$beanstalk->put(0, 0, 60, '');

$beanstalk->disconnect();