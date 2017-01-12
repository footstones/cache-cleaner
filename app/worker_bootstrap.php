<?php

include dirname(__DIR__).'/vendor/autoload.php';

use Codeages\Beanstalk\Client;

$beanstalk = new Client(array_merge(
    $config['message_server'],
    ['persistent' => false]
));
$beanstalk->connect();

$beanstalk->useTube('cache_cleaner');

while ($job = $beanstalk->peekReady()) {
    $beanstalk->delete($job['id']);
}
while ($job = $beanstalk->peekDelayed()) {
    $beanstalk->delete($job['id']);
}
while ($job = $beanstalk->peekBuried()) {
    $beanstalk->delete($job['id']);
}

$beanstalk->put(0, 0, 60, '');

$beanstalk->disconnect();