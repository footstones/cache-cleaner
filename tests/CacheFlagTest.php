<?php

namespace Footstones\CacheCleaner\Tests;

use Footstones\CacheCleaner\CacheFlag;


class CacheFlagTest extends \PHPUnit_Framework_TestCase
{
    public function testFlag()
    {
        $options = include __DIR__ . '/../app/config.php';

        $flag = new CacheFlag($options, 'test_scope');

        $flag->flag('testkey');
    }

}



