<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use Mammatus\Cron\App;
use Mammatus\Cron\BuildIn\Noop;
use Mammatus\ExitCode;
use Mammatus\LifeCycleEvents\Shutdown;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function array_key_exists;

final class AppTest extends AsyncTestCase
{
    #[Test]
    public function runHappy(): void
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Noop::class)->once()->andReturn(new Noop());

        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->expects('dispatch')->withArgs(static fn (Shutdown $event): bool => true)->once();

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Noop::class])->once();
        $logger->expects('log')->with('debug', 'Starting job', ['cronjob' => Noop::class])->once();
        $logger->expects('log')->with('debug', 'Job finished', ['cronjob' => Noop::class])->once();
        $logger->expects('log')->with('info', 'Dispatching shutdown event', ['cronjob' => Noop::class])->once();
        $logger->expects('log')->with('debug', 'Shutdown event dispatched', ['cronjob' => Noop::class])->once();

        $exitCode = (new App($container, $eventDispatcher, $logger))->boot(new App\Cron(Noop::class));

        self::assertSame(ExitCode::Success, $exitCode);
    }

    #[Test]
    public function runAngry(): void
    {
        $exception = new RuntimeException('Ik ben boos!');
        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Angry::class)->once()->andReturn(new Angry($exception));

        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->expects('dispatch')->withArgs(static fn (Shutdown $event): bool => true)->once();

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Angry::class])->once();
        $logger->expects('log')->with('debug', 'Starting job', ['cronjob' => Angry::class])->once();
        $logger->expects('log')->with('error', 'Job errored: {exception_message}', ['cronjob' => Angry::class, 'exception' => $exception, 'exception_message' => $exception->getMessage()])->once();

        $exitCode = (new App($container, $eventDispatcher, $logger))->boot(new App\Cron(Angry::class));

        self::assertSame(ExitCode::Failure, $exitCode);
    }

    #[Test]
    public function runNonAction(): void
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Sad::class)->once()->andReturn(new Sad());

        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->expects('dispatch')->withArgs(static fn (Shutdown $event): bool => true)->once();

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Sad::class])->once();
        $logger->expects('log')->withArgs(static fn (string $type, string $message, array $context): bool => array_key_exists('cronjob', $context) && $context['cronjob'] === Sad::class && array_key_exists('exception', $context) && $context['exception'] instanceof RuntimeException && $context['exception']->getMessage() === 'Given job is not an action')->once();

        $exitCode = (new App($container, $eventDispatcher, $logger))->boot(new App\Cron(Sad::class));

        self::assertSame(ExitCode::Failure, $exitCode);
    }
}
