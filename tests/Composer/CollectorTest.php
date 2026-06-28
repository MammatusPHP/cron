<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Composer;

use Mammatus\Cron\Action\Type;
use Mammatus\Cron\Composer\Collector;
use Mammatus\Cron\Composer\Item;
use Mammatus\DevApp\Cron\Noop;
use Mammatus\DevApp\Cron\Yep;
use PHPUnit\Framework\Attributes\Test;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\TestUtilities\TestCase;

final class CollectorTest extends TestCase
{
    #[Test]
    public function internal(): void
    {
        $items = [...new Collector()->collect(ReflectionClass::createFromName(Noop::class))];

        self::assertCount(1, $items);

        foreach ($items as $item) {
            self::assertInstanceOf(Item::class, $item);
            self::assertSame(Noop::class, $item->class);
            self::assertSame(Type::Internal, $item->type);
            self::assertSame('no.op', $item->cron->name);
            self::assertSame(69.0, $item->cron->ttl);
            self::assertSame('* * * * *', $item->cron->schedule);
            self::assertCount(0, $item->cron->addOns);
        }
    }

    #[Test]
    public function kubernetes(): void
    {
        $items = [...new Collector()->collect(ReflectionClass::createFromName(Yep::class))];

        self::assertCount(1, $items);

        foreach ($items as $item) {
            self::assertInstanceOf(Item::class, $item);
            self::assertSame(Yep::class, $item->class);
            self::assertSame(Type::Kubernetes, $item->type);
            self::assertSame('ye.et', $item->cron->name);
            self::assertSame(69.0, $item->cron->ttl);
            self::assertSame('* * * * *', $item->cron->schedule);
            self::assertCount(0, $item->cron->addOns);
        }
    }
}
