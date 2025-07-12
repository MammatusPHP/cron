<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use Mammatus\Cron\BuildIn\Noop;
use Mammatus\Cron\Manager;
use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Mutex\Memory;
use WyriHaximus\React\PHPUnit\TimeOut;

use function array_key_exists;
use function React\Async\await;
use function React\Promise\Timer\sleep;

#[TimeOut(133)]
final class ManagerTest extends AsyncTestCase
{
    #[Test]
    public function runHappy(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('debug')->with('Starting cron manager')->once();
        $logger->expects('debug')->with('Started cron manager')->once();
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Noop::class])->atLeast()->once();
        $logger->expects('log')->with('debug', 'Starting job', ['cronjob' => Noop::class])->atLeast()->once();
        $logger->expects('log')->with('debug', 'Job finished', ['cronjob' => Noop::class])->atLeast()->once();
        $logger->expects('debug')->with('Stopping cron manager')->once();
        $logger->expects('debug')->with('Stopped cron manager')->once();

        $mutex = new Memory();

        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Noop::class)->atLeast()->once()->andReturn(new Noop());

        $manager = new Manager(
            $logger,
            $mutex,
            $container,
        );
        $manager->start(new Initialize());
        await(sleep(99));
        $manager->stop(new Shutdown());
    }

    #[Test]
    public function runAngry(): void
    {
        $exception = new RuntimeException('Ik ben boos!');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('debug')->with('Starting cron manager')->once();
        $logger->expects('debug')->with('Started cron manager')->once();
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Noop::class])->atLeast()->once();
        $logger->expects('log')->with('debug', 'Starting job', ['cronjob' => Noop::class])->atLeast()->once();
        $logger->expects('log')->with('error', 'Job errored', ['cronjob' => Noop::class, 'exception' => $exception])->atLeast()->once();
        $logger->expects('debug')->with('Stopping cron manager')->once();
        $logger->expects('debug')->with('Stopped cron manager')->once();

        $mutex = new Memory();

        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Noop::class)->atLeast()->once()->andReturn(new Angry($exception));

        $manager = new Manager(
            $logger,
            $mutex,
            $container,
        );
        $manager->start(new Initialize());
        await(sleep(99));
        $manager->stop(new Shutdown());
    }

    #[Test]
    public function notAnAction(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('debug')->with('Starting cron manager')->once();
        $logger->expects('debug')->with('Started cron manager')->once();
        $logger->expects('log')->with('debug', 'Getting job', ['cronjob' => Noop::class])->atLeast()->once();
        $logger->expects('log')->withArgs(static function (string $type, string $error, array $context): bool {
            if ($type !== 'error') {
                return false;
            }

            if ($error !== 'Job errored') {
                return false;
            }

            if (! (array_key_exists('cronjob', $context) && $context['cronjob'] === Noop::class)) {
                return false;
            }

            return array_key_exists('exception', $context) && $context['exception'] instanceof Throwable && $context['exception']->getMessage() === 'Given job is not an action';
        })->atLeast()->once();
        $logger->expects('debug')->with('Stopping cron manager')->once();
        $logger->expects('debug')->with('Stopped cron manager')->once();

        $mutex = new Memory();

        $container = Mockery::mock(ContainerInterface::class);
        $container->expects('get')->with(Noop::class)->atLeast()->once()->andReturn(new Sad());

        $manager = new Manager(
            $logger,
            $mutex,
            $container,
        );
        $manager->start(new Initialize());
        await(sleep(99));
        $manager->stop(new Shutdown());
    }
}
