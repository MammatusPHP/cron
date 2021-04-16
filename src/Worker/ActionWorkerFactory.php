<?php

declare(strict_types=1);

namespace Mammatus\Cron\Worker;

use Firehed\SimpleLogger\Stdout;
use Mammatus\ContainerFactory;
use Psr\Log\LoggerInterface;
use ReactParallel\Pool\Worker\Work\Worker;
use ReactParallel\Pool\Worker\Work\WorkerFactory;
use ReactParallel\Psr11ContainerProxy\ContainerProxy;
use ReactParallel\Psr11ContainerProxy\OverridesProvider;
use WyriHaximus\Monolog\Factory;

final class ActionWorkerFactory implements WorkerFactory
{
    private OverridesProvider $overridesProvider;
    private ContainerProxy $proxy;

    public function __construct(OverridesProvider $overridesProvider, ContainerProxy $proxy)
    {
        $this->overridesProvider = $overridesProvider;
        $this->proxy = $proxy;
    }

    public function construct(): Worker
    {
        $overrides = [];

        foreach ($this->overridesProvider->list() as $from => $to) {
            $overrides[$from] = fn (): object => $this->proxy->proxy()->get($to);
        }

        $overrides[LoggerInterface::class] = static fn (): LoggerInterface => Factory::create('cron', new Stdout(), []);

        return new ActionWorker($this->proxy->create(ContainerFactory::create($overrides)));
    }
}
// phpcs:enable
