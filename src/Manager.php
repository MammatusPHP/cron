<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Generated\AbstractManager;
use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\PSR3\ContextLogger\ContextLogger;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;

final class Manager extends AbstractManager implements Listener
{
    private LoggerInterface $logger;
    private MutexInterface $mutex;
    private ContainerInterface $container;

    public function __construct(LoggerInterface $logger, MutexInterface $mutex, ContainerInterface $container)
    {
        $this->logger    = $logger;
        $this->mutex     = $mutex;
        $this->container = $container;
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
        $logger = new ContextLogger($this->logger, ['cronjob' => $class]);
        try {
            $logger->debug('Getting job');
            $job = $this->container->get($class);
            $logger->debug('Starting job');
            $job->perform();
            $logger->debug('Job finished');
        } catch (Throwable $throwable) {
            $logger->error('Job errored', ['exception' => $throwable]);
        }
    }
}
