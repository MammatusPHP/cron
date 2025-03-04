<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Contracts\Argv;
use Mammatus\Contracts\Bootable;
use Mammatus\Cron\App\Cron;
use Mammatus\Cron\Contracts\Action;
use Mammatus\ExitCode;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use WyriHaximus\PSR3\ContextLogger\ContextLogger;

/** @implements Bootable<Cron> */
final class App implements Bootable
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function boot(Argv $argv): ExitCode
    {
        $logger = new ContextLogger($this->logger, ['cronjob' => $argv->className]);
        try {
            $logger->debug('Getting job');
            $job = $this->container->get($argv->className);
            if (! ($job instanceof Action)) {
                throw new RuntimeException('Given job is not an action');
            }

            $logger->debug('Starting job');
            $job->perform();
            $logger->debug('Job finished');

            $this->eventDispatcher->dispatch(new Shutdown());

            return ExitCode::Success;
        } catch (Throwable $throwable) { /** @phpstan-ignore-line */
            $logger->error('Job errored: ' . $throwable->getMessage(), ['exception' => $throwable]);

            $this->eventDispatcher->dispatch(new Shutdown());

            return ExitCode::Failure;
        }
    }
}
