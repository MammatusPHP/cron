<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use Mammatus\Cron\Generated\AbstractList_;

use function Safe\json_encode;

final class List_ extends AbstractList_
{
    public function list(string $type): int
    {
        $crons = [];
        foreach ($this->crons() as $action) {
            $crons[] = $action;
        }

        echo json_encode($crons);

        return 0;
    }
}
