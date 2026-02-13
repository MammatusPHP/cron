<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Generated\Kubernetes\Helm;

use Mammatus\Cron\Generated\Kubernetes\Helm\CronJobsValues;
use Mammatus\DevApp\Cron\Yep;
use Mammatus\Kubernetes\Events\Helm\Values;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use const DIRECTORY_SEPARATOR;

final class CronJobsValuesTest extends TestCase
{
    #[Test]
    public function all(): void
    {
        $values = new Values(
            new Values\Groups(),
            new Values\Registry(),
            Values\ValuesFile::createFromFile(__DIR__ . DIRECTORY_SEPARATOR . 'values.yaml'),
        );
        new CronJobsValues()->values($values);

        self::assertSame([
            'cronjobs' => [
                'cron-ye-et' => [
                    'name' => 'cron-ye-et',
                    'class' => Yep::class,
                    'schedule' => '* * * * *',
                    'addOns' => [],
                ],
            ], // Empty array here because we don't have any default cronjobs running in Kubernetes out of the box
        ], $values->get());
    }
}
