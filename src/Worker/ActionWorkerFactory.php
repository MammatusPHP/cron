<?php

declare(strict_types=1);

namespace Mammatus\Cron\Worker;

use Firehed\SimpleLogger\Stdout;
use Mammatus\ContainerFactory;
use Psr\Log\LoggerInterface;
use ReactParallel\ObjectProxy\ProxyListInterface;
use ReactParallel\Pool\Worker\Work\Worker;
use ReactParallel\Pool\Worker\Work\WorkerFactory;
use ReactParallel\Psr11ContainerProxy\ContainerProxy;
use WyriHaximus\Monolog\Factory;

final class ActionWorkerFactory implements WorkerFactory
{
    private ProxyListInterface $proxyList;
    private ContainerProxy $proxy;

    public function __construct(ProxyListInterface $proxyList, ContainerProxy $proxy)
    {
        $this->proxyList = $proxyList;
        $this->proxy = $proxy;
    }

    public function construct(): Worker
    {
        $overrides = [];

        foreach ($this->proxyList->interfaces() as $interface) {
            $overrides[$interface] = fn (): object => $this->proxy->proxy()->get($interface);
        }

        foreach ($this->proxyList->noPromiseKnownInterfaces() as $noPromiseInterface => $interface) {
            $overrides[$noPromiseInterface] = fn (): object => $this->proxy->proxy()->get($interface);
        }

        $overrides[LoggerInterface::class] = static fn (): LoggerInterface => Factory::create('cron', new Stdout(), []);

        return new ActionWorker($this->proxy->create(ContainerFactory::create($overrides)));
    }
}
// phpcs:enable
