<?php

declare(strict_types=1);

namespace Mammatus\Cron\Kubernetes\Helm;

use Mammatus\Cron\Action;
use Mammatus\Cron\Generated\AbstractList_;
use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\Broadcast\Contracts\Listener;

use function array_filter;
use function array_map;
use function str_replace;

final class CronJobsValues extends AbstractList_ implements Listener
{
    public function values(Values $values): void
    {
        $values->registry->add(
            'cronjobs',
            array_map(
                static fn (Action $action): array => [
                    'name' => 'cron-' . str_replace('.', '-', $action->name),
                    'schedule' => $action->schedule,
                    'class' => $action->class,
                    'addOns' => $action->addOns,
                ],
                array_filter(
                    [...$this->crons()],
                    static fn (Action $action): bool => $action->type === 'kubernetes',
                ),
            ),
        );
    }
}
