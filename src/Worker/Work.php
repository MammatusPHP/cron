<?php

declare(strict_types=1);

namespace Mammatus\Cron\Worker;

use ReactParallel\Pool\Worker\Work\Work as WorkContract;

final class Work implements WorkContract
{
    private ClassString $class;

    public function __construct(string $class)
    {
        $this->class = new ClassString($class);
    }

    public function work(): ClassString
    {
        return $this->class;
    }
}
