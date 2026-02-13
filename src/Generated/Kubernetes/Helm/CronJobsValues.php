<?php

declare(strict_types=1);

namespace Mammatus\Cron\Generated\Kubernetes\Helm;

use Mammatus\DevApp\Cron\Yep;
use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\Broadcast\Contracts\Listener;

use function json_decode;

final class CronJobsValues implements Listener
{
    public function values(Values $values): void
    {
        $values->add(
            new Values\Registry\CronJob(
                'cron-ye-et',
                Yep::class,
                '* * * * *',
                json_decode('[]', true),
            ),
        );
    }
}
