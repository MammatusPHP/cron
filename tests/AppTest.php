<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use Mammatus\Cron\App;
use Mammatus\Cron\BuildIn\Noop;
use Mammatus\Cron\Performer;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

final class AppTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function runHappy(): void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(Noop::class)->shouldBeCalled()->willReturn(new Noop());

        $exitCode = (new App(new Performer($container->reveal(), new NullLogger())))->run(Noop::class);

        self::assertSame(0, $exitCode);
    }

    /**
     * @test
     */
    public function runAngry(): void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(Angry::class)->shouldBeCalled()->willReturn(new Angry(new RuntimeException('Ik ben boos!')));

        $exitCode = (new App(new Performer($container->reveal(), new NullLogger())))->run(Angry::class);

        self::assertSame(1, $exitCode);
    }

    /**
     * @test
     */
    public function runNonAction(): void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(Sad::class)->shouldBeCalled()->willReturn(new Sad());

        $exitCode = (new App(new Performer($container->reveal(), new NullLogger())))->run(Sad::class);

        self::assertSame(1, $exitCode);
    }
}
