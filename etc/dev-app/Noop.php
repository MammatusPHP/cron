<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Cron;

use Mammatus\Cron\Attributes\Cron;
use Mammatus\Cron\Contracts\Action;

use function WyriHaximus\React\timedPromise;

#[Cron(name: 'no.op', ttl: 69, schedule: '* * * * *')]
final class Noop implements Action
{
    private const int INTERVAL = 3;

    public function perform(): void
    {
        timedPromise(self::INTERVAL);
    }
}
