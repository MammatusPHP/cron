<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Contracts\Action;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use WyriHaximus\PSR3\ContextLogger\ContextLogger;

use function React\Async\async;
use function React\Async\await;

final class App
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(string $className): int
    {
        return await(async(function (string $className): int {
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

                return 0;
            } catch (Throwable $throwable) { /** @phpstan-ignore-line */
                $logger->error('Job errored: ' . $throwable->getMessage(), ['exception' => $throwable]);

                return 1;
            }
        })($className));
    }
}
