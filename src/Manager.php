<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Generated\AbstractManager;
use Mammatus\Cron\Worker\ActionWorkerFactory;
use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use ReactParallel\Factory;
use ReactParallel\Pool\Worker\Worker as WorkerPool;
use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;

final class Manager extends AbstractManager implements Listener
{
    private LoggerInterface $logger;
    private LoopInterface $loop;
    private MutexInterface $mutex;
    private WorkerPool $workerPool;

    public function __construct(LoggerInterface $logger, LoopInterface $loop, MutexInterface $mutex, ContainerInterface $container)
    {
        $this->logger     = $logger;
        $this->loop       = $loop;
        $this->mutex      = $mutex;
        $this->workerPool = new WorkerPool(
            $container->get(Factory::class),
            $container->get(ActionWorkerFactory::class),
            (int) '13'
        );
    }

    public function start(Initialize $event): void
    {
        $this->logger->debug('Starting cron manager');
        $this->cron($this->loop, $this->mutex, $this->workerPool);
        $this->logger->debug('Started cron manager');
    }

    public function stop(Shutdown $event): void
    {
        $this->logger->debug('Stopping cron manager');
        $this->cron($this->loop, $this->mutex, $this->workerPool)->stop();
        $this->logger->debug('Stopped cron manager');
        $this->workerPool->close();
    }
}
