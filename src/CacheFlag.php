<?php

namespace Footstones\CacheCleaner;

use Doctrine\DBAL\DriverManager;

class CacheFlag
{
    protected $options;

    protected $scope;

    protected $connection;

    public function __construct($options, $scope)
    {
        $this->options = $options;
        $this->scope = $scope;

        $config = $this->options['database'];

        $this->connection = DriverManager::getConnection(array(
            'dbname' => $config['name'],
            'user' => $config['user'],
            'password' => $config['password'],
            'host' => $config['host'],
            'driver' => $config['driver'],
            'charset' => $config['charset'],
        ));
    }

    public function flag($key)
    {
        $flag = [];
        $flag['scope'] = $this->scope;
        $flag['keyname'] = $key;
        $flag['createdTime'] = time();

        $affected = $this->connection->insert($this->options['table'], $flag);

        return $affected > 0 ? true : false;
    }

}