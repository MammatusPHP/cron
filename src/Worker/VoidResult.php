<?php

declare(strict_types=1);

namespace Mammatus\Cron\Worker;

use ReactParallel\Pool\Worker\Work\Result;

final class VoidResult implements Result
{
    private ClassString $class;

    public function __construct(ClassString $class)
    {
        $this->class = $class;
    }

    public function result(): ClassString
    {
        return $this->class;
    }
}
