<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use JsonSerializable;
use Mammatus\Cron\Action\Type;
use Mammatus\Cron\Attributes\Cron;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Item implements ItemContract, JsonSerializable
{
    /** @param class-string $class */
    public function __construct(
        public string $class,
        public Cron $cron,
        public Type $type,
    ) {
    }

    /** @return array{class: class-string, cron: Cron, type: Type} */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'cron' => $this->cron,
            'type' => $this->type,
        ];
    }
}
