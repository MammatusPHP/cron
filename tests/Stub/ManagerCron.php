<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Stub;

use Mammatus\Cron\Manager;
use Mammatus\DevApp\Cron\Noop;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;

// phpcs:disable
final class ManagerCron
{
    public static function inject(Manager $manager, MutexInterface $mutex, ClockInterface $clock): void
    {
        $reflection    = new ReflectionClass(Manager::class);
        $performMethod = $reflection->getMethod('perform');
        $performMethod->setAccessible(true);

        $cron = Cron::createWithClockAndMutex(
            $mutex,
            $clock,
            new Cron\Action(
                'cron_no.op',
                69,
                '* * * * *',
                static fn () => $performMethod->invoke($manager, Noop::class),
            ),
        );

        $cronProperty = $reflection->getProperty('cron');
        $cronProperty->setAccessible(true);
        $cronProperty->setValue($manager, $cron);
    }
}
