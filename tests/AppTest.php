<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use Mammatus\Cron\App;
use Mammatus\Cron\BuildIn\Noop;
use Mockery;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function array_key_exists;

final class AppTest extends AsyncTestCase
{
    /** @test */
    public function runHappy(): void
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Noop::class)->once()->andReturn(new Noop());

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Noop::class])->once();
        $logger->expects('log')->with('debug', 'Starting job', ['cronjob' => Noop::class])->once();
        $logger->expects('log')->with('debug', 'Job finished', ['cronjob' => Noop::class])->once();

        $exitCode = (new App($container, $logger))->run(Noop::class);

        self::assertSame(0, $exitCode);
    }

    /** @test */
    public function runAngry(): void
    {
        $exception = new RuntimeException('Ik ben boos!');
        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Angry::class)->once()->andReturn(new Angry($exception));

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Angry::class])->once();
        $logger->expects('log')->with('debug', 'Starting job', ['cronjob' => Angry::class])->once();
        $logger->expects('log')->with('error', 'Job errored', ['cronjob' => Angry::class, 'exception' => $exception])->once();

        $exitCode = (new App($container, $logger))->run(Angry::class);

        self::assertSame(1, $exitCode);
    }

    /** @test */
    public function runNonAction(): void
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Sad::class)->once()->andReturn(new Sad());

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Sad::class])->once();
        $logger->expects('log')->withArgs(static function (string $type, string $message, array $context): bool {
            return array_key_exists('cronjob', $context) && $context['cronjob'] === Sad::class && array_key_exists('exception', $context) && $context['exception'] instanceof RuntimeException && $context['exception']->getMessage() === 'Given job is not an action';
        })->once();

        $exitCode = (new App($container, $logger))->run(Sad::class);

        self::assertSame(1, $exitCode);
    }
}
