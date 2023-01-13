<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use Mammatus\Cron\BuildIn\Noop;
use Mammatus\Cron\Performer;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function array_key_exists;
use function microtime;

final class PerformerTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function runHappy(): void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(Noop::class)->shouldBeCalled()->willReturn(new Noop());

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->log('debug', 'Getting job', ['cronjob' => Noop::class])->shouldBeCalled();
        $logger->log('debug', 'Starting job', ['cronjob' => Noop::class])->shouldBeCalled();
        $logger->log('debug', 'Job finished', ['cronjob' => Noop::class])->shouldBeCalled();

        $start      = microtime(true);
        $successful = (new Performer($container->reveal(), $logger->reveal()))->run(Noop::class);
        $finish     = microtime(true);
        $took       = $finish - $start;
        $wait       = Noop::WAIT;

        self::assertTrue($successful);
        self::assertGreaterThanOrEqual($wait, $took);
        self::assertLessThan(++$wait, $took);
    }

    /**
     * @test
     */
    public function runAngry(): void
    {
        $exception = new RuntimeException('Ik ben boos!');
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(Angry::class)->shouldBeCalled()->willReturn(new Angry($exception));

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->log('debug', 'Getting job', ['cronjob' => Angry::class])->shouldBeCalled();
        $logger->log('debug', 'Starting job', ['cronjob' => Angry::class])->shouldBeCalled();
        $logger->log('error', 'Job errored', ['cronjob' => Angry::class, 'exception' => $exception])->shouldBeCalled();

        $successful = (new Performer($container->reveal(), $logger->reveal()))->run(Angry::class);

        self::assertFalse($successful);
    }

    /**
     * @test
     */
    public function runNonAction(): void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(Sad::class)->shouldBeCalled()->willReturn(new Sad());

        $logger = $this->prophesize(LoggerInterface::class);
        $logger->log('debug', 'Getting job', ['cronjob' => Sad::class])->shouldBeCalled();
        $logger->log('error', 'Job errored', Argument::that(static function (array $context) {
            return array_key_exists('cronjob', $context) && $context['cronjob'] === Sad::class && array_key_exists('exception', $context) && $context['exception'] instanceof RuntimeException && $context['exception']->getMessage() === 'Given job is not an action';
        }))->shouldBeCalled();

        $successful = (new Performer($container->reveal(), $logger->reveal()))->run(Sad::class);

        self::assertFalse($successful);
    }
}
