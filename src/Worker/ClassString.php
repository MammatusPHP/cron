<?php

declare(strict_types=1);

namespace Mammatus\Cron\Worker;

final class ClassString
{
    private string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    public function class(): string
    {
        return $this->class;
    }
}
