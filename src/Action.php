<?php

declare(strict_types=1);

namespace Mammatus\Cron;

final readonly class Action
{
    /** @param array<string, mixed> $addOns */
    public function __construct(
        public string $type,
        public string $name,
        public string $schedule,
        public string $class,
        public array $addOns,
    ) {
    }
}
