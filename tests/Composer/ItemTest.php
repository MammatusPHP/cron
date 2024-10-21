<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Composer;

use Mammatus\Cron\Attributes\Cron;
use Mammatus\Cron\Composer\Item;
use Mammatus\Kubernetes\Attributes\Resources;
use WyriHaximus\TestUtilities\TestCase;

use function Safe\json_encode;

final class ItemTest extends TestCase
{
    /** @test */
    public function json(): void
    {
        $item = new Item(
            Item::class,
            new Cron(
                'test',
                1337,
                '* * * * *',
                new Resources(
                    cpu: 0.666,
                    memory: 3,
                ),
            ),
            false,
        );
        self::assertSame(
            '{"class":"Mammatus\\\\Cron\\\\Composer\\\\Item","cron":{"addOns":[{"type":"container","helper":"mammatus.container.resources","arguments":{"cpu":"666m","memory":"3072Mi"}}],"name":"test","ttl":1337,"schedule":"* * * * *"},"split_out":false}',
            json_encode($item),
        );
    }
}
