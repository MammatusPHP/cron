<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\PHPSan;

use Mammatus\Cron\App\Cron;
use Mammatus\Cron\PHPSan\ShipMonkDeadCode;
use Mammatus\DevApp\Cron\Noop;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\TestUtilities\TestCase;

final class ShipMonkDeadCodeTest extends TestCase
{
    #[Test]
    public function marksActionMethodsAsUsed(): void
    {
        $usage = (new ShipMonkDeadCode())->shouldMarkMethodAsUsed(new ReflectionMethod(Noop::class, 'perform'));

        self::assertNotNull($usage);
        self::assertSame('Class is a Cron Action', $usage->getNote());
    }

    #[Test]
    public function doesNotMarkNonActionMethodsAsUsed(): void
    {
        self::assertNull((new ShipMonkDeadCode())->shouldMarkMethodAsUsed(new ReflectionMethod(Cron::class, '__construct')));
    }
}
