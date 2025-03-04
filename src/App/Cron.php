<?php

declare(strict_types=1);

namespace Mammatus\Cron\App;

use Mammatus\Contracts\Argv;
use Mammatus\Cron\Contracts\Action;

final readonly class Cron implements Argv
{
    /** @param class-string<Action> $className */
    public function __construct(
        public string $className,
    ) {
    }
}
