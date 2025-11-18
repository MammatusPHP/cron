<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Cron;

use Mammatus\Cron\Attributes\Cron;
use Mammatus\Cron\Contracts\Action;
use Mammatus\Kubernetes\Attributes\SplitOut;

use function WyriHaximus\React\timedPromise;

#[SplitOut]
#[Cron(name: 'ye.et', ttl: 69, schedule: '* * * * *')]
final class Yep implements Action
{
    private const int INTERVAL = 3;

    public function perform(): void
    {
        timedPromise(self::INTERVAL);
    }
}
