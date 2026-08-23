<?php declare(strict_types=1);

namespace Mammatus\Cron\Kubernetes\Helm;

use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\Broadcast\Contracts\Listener;

final class CronJobsValues implements Listener
{
    public function values(Values $values): void
    {
        $values->add(
            new Values\Registry\CronJob(
                'cron-ye-et',
                \Mammatus\DevApp\Cron\Yep::class,
                '* * * * *',
                \json_decode('[]', true), 
            ),
        );
            }
}
