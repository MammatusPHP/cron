<?php

declare(strict_types=1);

namespace Mammatus\Cron\BuildIn;

use Mammatus\Cron\Attributes\Cron;
use Mammatus\Cron\Contracts\Action;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use function WyriHaximus\React\timedPromise;

/**
 * @Cron(name="noop", ttl=120, schedule="* * * * *")
 */
final class Noop implements Action
{
    private LoopInterface $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function perform(): PromiseInterface
    {
        return timedPromise($this->loop, 3, true);
    }
}
