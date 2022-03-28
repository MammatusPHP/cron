<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Generated\AbstractManager;
use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\resolve;

final class Manager extends AbstractManager implements Listener
{
    private LoggerInterface $logger;
    private MutexInterface $mutex;
    private ContainerInterface $container;

    public function __construct(LoggerInterface $logger, MutexInterface $mutex, ContainerInterface $container)
    {
        $this->logger     = $logger;
        $this->mutex      = $mutex;
        $this->container      = $container;
    }

    public function start(Initialize $event): void
    {
        $this->logger->debug('Starting cron manager');
        $this->cron($this->mutex);
        $this->logger->debug('Started cron manager');
    }

    public function stop(Shutdown $event): void
    {
        $this->logger->debug('Stopping cron manager');
        $this->cron($this->mutex)->stop();
        $this->logger->debug('Stopped cron manager');
    }

    protected function perform(string $class): void
    {
        $this->logger->debug('Starting job: ' . $class);
        try {
            $this->container->get($class)->perform();
            $this->logger->debug('Job finished: ' . $class);
        } catch (\Throwable $throwable) {
            $this->logger->debug('Job errored: ' . $class . ' ' . (string)$throwable);
        }
    }
}
