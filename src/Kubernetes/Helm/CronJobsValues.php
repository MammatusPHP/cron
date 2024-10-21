<?php

declare(strict_types=1);

namespace Mammatus\Cron\Kubernetes\Helm;

use Mammatus\Cron\Action;
use Mammatus\Cron\Generated\AbstractList;
use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\Broadcast\Contracts\Listener;

use function array_filter;
use function array_map;
use function str_replace;

final readonly class CronJobsValues extends AbstractList implements Listener
{
    /** @phpstan-ignore-next-line This makes this class test able */
    public function __construct(
        private false|string $type = 'kubernetes',
    ) {
    }

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
                $this->type === false ? [...$this->crons()] : array_filter(
                    [...$this->crons()],
                    fn (Action $action): bool => $action->type === $this->type,
                ),
            ),
        );
    }
}
