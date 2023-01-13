<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Generated\AbstractManager;
use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Log\LoggerInterface;
use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;

final class Manager extends AbstractManager implements Listener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MutexInterface $mutex,
        private readonly Performer $performer,
    ) {
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
        $this->performer->run($class);
    }
}
