<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Kubernetes\Helm;

use Mammatus\Cron\BuildIn\Noop;
use Mammatus\Cron\Kubernetes\Helm\CronJobsValues;
use Mammatus\Kubernetes\Events\Helm\Values;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class CronJobsValuesTest extends TestCase
{
    #[Test]
    public function none(): void
    {
        $values = new Values(new Values\Registry());
        (new CronJobsValues())->values($values);

        self::assertSame([
            'cronjobs' => [], // Empty array here because we don't have any default cronjobs running in Kubernetes out of the box
        ], $values->registry->get());
    }

    #[Test]
    public function all(): void
    {
        $values = new Values(new Values\Registry());
        (new CronJobsValues(false))->values($values);

        self::assertSame([
            'cronjobs' => [
                'internal-no.op-Mammatus-Cron-BuildIn-Noop' => [
                    'name' => 'cron-no-op',
                    'schedule' => '* * * * *',
                    'class' => Noop::class,
                    'addOns' => [],
                ],
            ], // Empty array here because we don't have any default cronjobs running in Kubernetes out of the box
        ], $values->registry->get());
    }
}
