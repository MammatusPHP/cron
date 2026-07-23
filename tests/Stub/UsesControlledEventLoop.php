<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Stub;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use ReflectionClass;

// phpcs:disable
trait UsesControlledEventLoop
{
    private ControlledEventLoop $controlledEventLoop;

    protected function setUpControlledEventLoop(): void
    {
        $this->controlledEventLoop = new ControlledEventLoop();
        self::setGlobalEventLoop($this->controlledEventLoop);
    }

    protected function tearDownControlledEventLoop(): void
    {
        self::setGlobalEventLoop(new StreamSelectLoop());
    }

    protected function controlledEventLoop(): ControlledEventLoop
    {
        return $this->controlledEventLoop;
    }

    private static function setGlobalEventLoop(LoopInterface $loop): void
    {
        $reflection = new ReflectionClass(Loop::class);
        $property   = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, $loop);
    }
}
