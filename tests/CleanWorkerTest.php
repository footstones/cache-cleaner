<?php
namespace Footstones\CacheCleaner\Tests;

use Footstones\CacheCleaner\CleanWorker;

class CleanWorkerTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $config = include __DIR__ . '/../app/worker_config.php';

        $worker = new CleanWorker('cache_cleaner', $config['tubes']['cache_cleaner']);

        $job = [];

        $worker->execute($job);
    }

}
