<?php

declare(strict_types=1);

namespace Mammatus\Cron\Kubernetes\Helm;

use Mammatus\Cron\Action;
use Mammatus\Cron\Generated\AbstractList;
use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\Broadcast\Contracts\Listener;

use function array_filter;
use function str_replace;

final readonly class CronJobsValues extends AbstractList implements Listener
{
    /** @phpstan-ignore-next-line This makes this class test able */
    public function __construct(
        private Action\Type $type = Action\Type::Kubernetes,
    ) {
    }

    public function values(Values $values): void
    {
        foreach (
            array_filter(
                [...$this->crons()],
                fn (Action $action): bool => $action->type === $this->type,
            ) as $action
        ) {
            $values->add(
                new Values\Registry\CronJob(
                    'cron-' . str_replace('.', '-', $action->name),
                    $action->class,
                    $action->schedule,
                    $action->addOns,
                ),
            );
        }
    }
}
