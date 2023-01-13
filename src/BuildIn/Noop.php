<?php

declare(strict_types=1);

namespace Mammatus\Cron\BuildIn;

use Mammatus\Cron\Attributes\Cron;
use Mammatus\Cron\Contracts\Action;

use function React\Async\await;
use function WyriHaximus\React\timedPromise;

/**
 * @Cron(name="noop", ttl=120, schedule="* * * * *")
 */
final class Noop implements Action
{
    public const WAIT = 3;

    public function perform(): void
    {
        await(timedPromise(self::WAIT, true));
    }
}
