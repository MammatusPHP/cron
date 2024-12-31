<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Contracts\Action;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use RuntimeException;
use Throwable;
use WyriHaximus\PSR3\ContextLogger\ContextLogger;

use function React\Async\async;
use function React\Async\await;

final class App
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(string $className): int
    {
        $exitCode = 2;
        async(function (string $className): int {
            $logger = new ContextLogger($this->logger, ['cronjob' => $className]);
            try {
                $logger->debug('Getting job');
                $job = $this->container->get($className);
                if (! ($job instanceof Action)) {
                    throw new RuntimeException('Given job is not an action');
                }

                $logger->debug('Starting job');
                $job->perform();
                $logger->debug('Job finished');

                $this->eventDispatcher->dispatch(new Shutdown());

                return 0;
            } catch (Throwable $throwable) { /** @phpstan-ignore-line */
                $logger->error('Job errored: ' . $throwable->getMessage(), ['exception' => $throwable]);

                $this->eventDispatcher->dispatch(new Shutdown());

                return 1;
            }
        })($className)->then(static function (int $resultingExitCode)use (&$exitCode): void {
            $exitCode = $resultingExitCode;
        });

        Loop::run();

        return $exitCode;
    }
}
