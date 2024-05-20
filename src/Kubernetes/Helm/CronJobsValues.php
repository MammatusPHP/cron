<?php

declare(strict_types=1);

namespace Mammatus\Cron\Kubernetes\Helm;

use Mammatus\Cron\Generated\AbstractList_;
use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\Broadcast\Contracts\Listener;

use function array_filter;

final class CronJobsValues extends AbstractList_ implements Listener
{
    public function values(Values $values): void
    {
        $values->registry->add(
            'cronjobs',
            array_filter(
                [...$this->crons()],
                static fn (array $action): bool => $action['type'] === 'kubernetes',
            ),
        );
    }
}
