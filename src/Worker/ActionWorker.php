<?php

declare(strict_types=1);

namespace Mammatus\Cron\Worker;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReactParallel\Pool\Worker\Work\Work;
use ReactParallel\Pool\Worker\Work\Work as WorkContract;
use ReactParallel\Pool\Worker\Work\Worker;
use WyriHaximus\PSR3\CallableThrowableLogger\CallableThrowableLogger;

use function assert;

final class ActionWorker implements Worker
{
    private LoggerInterface $logger;
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
    }

    public function perform(WorkContract $work): VoidResult
    {
        $classString = $work->work();
        assert($classString instanceof ClassString);
        try {
            $this->logger->debug('Cron "' . $classString->class() . '" starting');
            $this->container->get($classString->class())->perform();
            $this->logger->debug('Cron "' . $classString->class() . '" finished');
        } catch (\Throwable $throwable) {
            $this->logger->error('Cron "' . $classString->class() . '" errored');
            CallableThrowableLogger::create($this->logger)($throwable);
        }

        return new VoidResult($classString);
    }
}
