<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Action\Type;

final readonly class Action
{
    /** @param array<string, mixed> $addOns */
    public function __construct(
        public Type $type,
        public string $name,
        public string $schedule,
        public string $class,
        public array $addOns,
    ) {
    }
}
