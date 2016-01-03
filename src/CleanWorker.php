<?php
namespace Footstones\CacheCleaner;

use Footstones\Plumber\IWorker;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\DriverManager;

class CleanWorker implements IWorker
{
    protected $logger = null;

    protected $scopeRedis = [];

    protected $connection;

    const DELAY = 2;

    public function __construct($tubeName, $config)
    {
        $this->tubeName = $tubeName;
        $this->config = $config;
    }

    public function execute($job)
    {
        try {

            $connection = $this->getConnection();

            $cursor = $this->getCursor();

            $sql = "SELECT * FROM {$this->config['table']} WHERE id > $cursor ORDER BY id ASC LIMIT 100";

            $flags = $connection->fetchAll($sql);
            if (empty($flags)) {
                $lastFlag = null;
                goto end;
            }

            $scopeKeys = [];

            foreach ($flags as $flag) {
                if (empty($scopeKeys[$flag['scope']])) {
                    $scopeKeys[$flag['scope']] = [];
                }
                $scopeKeys[$flag['scope']][] = $flag['keyname'];
            }

            $deletedNum = 0;

            foreach ($scopeKeys as $scope => $keys) {
                $redis = $this->getScopeRedis($scope);
                $deletedNum += $redis->del($keys);
                $redis->close();
            }

            $lastFlag = end($flags);

            $this->saveCursor($lastFlag);

            end:

            if ($lastFlag) {
                $message = "Last cursor {$lastFlag['id']}, deleted cache key num: {$deletedNum}.";
            } else {
                $message = "Last cursor: {$cursor}, no new cache key to delete.";
            }

            $this->logger && $this->logger->info($message);
            return array('code' => IWorker::RETRY, 'delay'=> self::DELAY);

        } catch (\Exception $e) {
            $this->logger && $this->logger->error("job #{$job['id']} throw exception: {$e->getMessage()}", $job);
            return array('code' => IWorker::RETRY, 'delay'=> self::DELAY);
        }

    }

    protected function getConnection()
    {
        if (empty($this->connection)) {
            $config = $this->config['database'];

            $this->connection = DriverManager::getConnection(array(
                'dbname' => $config['name'],
                'user' => $config['user'],
                'password' => $config['password'],
                'host' => $config['host'],
                'driver' => $config['driver'],
                'charset' => $config['charset'],
            ));

        }

        if ($this->connection->ping() === false) {
            $this->logger->info("mysql reconncetion. ");
            $this->connection->close();
            $this->connection->connect();
        }

        return $this->connection;
    }

    protected function getCursor()
    {
        $path = $this->config['cursor_file'];
        if (!file_exists($path)) {
            return 0;
        }
        return intval(file_get_contents($path));
    }

    protected function saveCursor($flag)
    {
        file_put_contents($this->config['cursor_file'], $flag['id']);
    }

    protected function getScopeRedis($scope)
    {
            $redis = new \Redis();
            $config = $this->config['scops'][$scope];
            $redis->connect($config['host'], $config['port']);
            return $redis;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function getJobService()
    {
        return Kernel::instance()->service('JobService');
    }
}