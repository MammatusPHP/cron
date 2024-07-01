<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use JsonSerializable;
use Mammatus\Cron\Attributes\Cron;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Item implements ItemContract, JsonSerializable
{
    /** @param class-string $class */
    public function __construct(
        public string $class,
        public Cron $cron,
        public bool $splitOut,
    ) {
    }

    /** @return array{class: class-string, cron: Cron, split_out: bool} */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'cron' => $this->cron,
            'split_out' => $this->splitOut,
        ];
    }
}
