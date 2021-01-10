<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Generated\AbstractManager;
use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use WyriHaximus\React\Mutex\MutexInterface;

final class Manager extends AbstractManager
{
    private LoggerInterface $logger;
    private LoopInterface $loop;
    private MutexInterface $mutex;
    private ContainerInterface $container;

    public function start(Initialize $event): void
    {
        $this->logger->debug('Starting cron manager');
        $this->cron($this->loop, $this->mutex, $this->container);
        $this->logger->debug('Started cron manager');
    }

    public function stop(Shutdown $event): void
    {
        $this->logger->debug('Stopping cron manager');
        $this->cron($this->loop, $this->mutex, $this->container)->stop();
        $this->logger->debug('Stopped cron manager');
    }
}