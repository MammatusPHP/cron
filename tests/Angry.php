<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use Mammatus\Cron\Contracts\Action;
use Throwable;

final readonly class Angry implements Action
{
    public function __construct(private Throwable $angry)
    {
    }

    public function perform(): void
    {
        throw $this->angry;
    }
}
