<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Kubernetes\Helm;

use Mammatus\Cron\Kubernetes\Helm\CronJobsValues;
use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\TestUtilities\TestCase;

final class CronJobsValuesTest extends TestCase
{
    /** @test */
    public function none(): void
    {
        $values = new Values(new Values\Registry());
        (new CronJobsValues())->values($values);

        self::assertSame([
            'cronjobs' => [], // Empty array here because we don't have any default cronjobs running in Kubernetes out of the box
        ], $values->registry->get());
    }

    /** @test */
    public function all(): void
    {
        $values = new Values(new Values\Registry());
        (new CronJobsValues(false))->values($values);

        self::assertSame([
            'cronjobs' => [
                'internal-no.op-Mammatus-Cron-BuildIn-Noop' => [
                    'name' => 'cron-no-op',
                    'schedule' => '* * * * *',
                    'class' => 'Mammatus\Cron\BuildIn\Noop',
                    'addOns' => [],
                ],
            ], // Empty array here because we don't have any default cronjobs running in Kubernetes out of the box
        ], $values->registry->get());
    }
}
