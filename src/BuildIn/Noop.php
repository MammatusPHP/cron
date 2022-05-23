<?php

declare(strict_types=1);

namespace Mammatus\Cron\BuildIn;

use Mammatus\Cron\Attributes\Cron;
use Mammatus\Cron\Contracts\Action;

use function WyriHaximus\React\timedPromise;

/**
 * @Cron(name="noop", ttl=120, schedule="* * * * *")
 */
final class Noop implements Action
{
    public function perform(): void
    {
        timedPromise(3, true);
    }
}
