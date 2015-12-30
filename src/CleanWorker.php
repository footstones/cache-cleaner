<?php
namespace Footstones\CacheCleaner;

use Footstones\Plumber\IWorker;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\DriverManager;

class CleanWorker implements IWorker
{
    protected $logger = null;

    protected $scopeRedis = [];

    public function __construct($tubeName, $config)
    {
        $this->tubeName = $tubeName;
        $this->config = $config;
    }

    public function execute($job)
    {

        try {
            $config = $this->config['database'];

            $connection = DriverManager::getConnection(array(
                'dbname' => $config['name'],
                'user' => $config['user'],
                'password' => $config['password'],
                'host' => $config['host'],
                'driver' => $config['driver'],
                'charset' => $config['charset'],
            ));

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
                $deletedNum += $this->getScopeRedis($scope)->del($keys);
            }

            $lastFlag = end($flags);

            $this->saveCursor($lastFlag);

            end:
            $connection->close();

            if ($lastFlag) {
                $message = "Last cursor {$lastFlag['id']}, deleted cache key num: {$deletedNum}.";
            } else {
                $message = "Last cursor: {$cursor}, no new cache key to delete.";
            }

            $this->logger && $this->logger->info($message);

            return IWorker::RETRY;
        } catch (\Exception $e) {
            $this->logger && $this->logger->error("job #{$job['id']} throw exception: {$e->getMessage()}", $job);
            return IWorker::RETRY;
        }

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
        if (empty($this->scopeRedis[$scope])) {
            $redis = new \Redis();
            $config = $this->config['scops'][$scope];
            $redis->connect($config['host'], $config['port']);
            $this->scopeRedis[$scope] = $redis;
        }

        return $this->scopeRedis[$scope];
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