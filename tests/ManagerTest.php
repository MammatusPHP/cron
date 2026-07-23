<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use DateTimeImmutable;
use DateTimeZone;
use Lcobucci\Clock\FrozenClock;
use Mammatus\Cron\Manager;
use Mammatus\DevApp\Cron\Noop;
use Mammatus\LifeCycleEvents\Boot;
use Mammatus\LifeCycleEvents\Shutdown;
use Mammatus\Tests\Cron\Stub\ManagerCron;
use Mammatus\Tests\Cron\Stub\UsesControlledEventLoop;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\ActionInterface;
use WyriHaximus\React\Cron\Scheduler;
use WyriHaximus\React\Mutex\Memory;
use WyriHaximus\React\PHPUnit\TimeOut;

use function array_key_exists;

#[TimeOut(5)]
final class ManagerTest extends AsyncTestCase
{
    use UsesControlledEventLoop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpControlledEventLoop();
    }

    protected function tearDown(): void
    {
        $this->tearDownControlledEventLoop();

        parent::tearDown();
    }

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
        ManagerCron::inject($manager, $mutex, self::cronClock());
        $manager->start(new Boot());
        $this->controlledEventLoop()->runUntilIdle();
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
        ManagerCron::inject($manager, $mutex, self::cronClock());
        $manager->start(new Boot());
        $this->controlledEventLoop()->runUntilIdle();
        $manager->stop(new Shutdown());
    }

    #[Test]
    public function stopStopsCron(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->allows('debug');

        $mutex     = new Memory();
        $container = Mockery::mock(ContainerInterface::class);

        $manager = new Manager(
            $logger,
            $mutex,
            $container,
        );
        ManagerCron::inject($manager, $mutex, self::cronClock());
        $manager->start(new Boot());

        $scheduler = self::cronScheduler(self::managerCron($manager));
        self::assertTrue(self::isSchedulerActive($scheduler));

        $manager->stop(new Shutdown());

        self::assertFalse(self::isSchedulerActive($scheduler));
    }

    #[Test]
    public function startInitializesCron(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->allows('debug');

        $mutex     = new Memory();
        $container = Mockery::mock(ContainerInterface::class);

        $manager = new Manager(
            $logger,
            $mutex,
            $container,
        );

        $reflection = new ReflectionClass(Manager::class);
        $property   = $reflection->getProperty('cron');
        $property->setAccessible(true);
        self::assertNull($property->getValue($manager));

        $manager->start(new Boot());

        $cron = self::managerCron($manager);
        self::assertCount(1, self::cronActions($cron));
        self::assertSame('cron_no.op', self::cronActions($cron)[0]->key());
        self::assertSame(69.0, self::cronActions($cron)[0]->mutexTtl());
    }

    #[Test]
    public function startReusesInjectedCron(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->allows('debug');

        $mutex     = new Memory();
        $container = Mockery::mock(ContainerInterface::class);

        $manager = new Manager(
            $logger,
            $mutex,
            $container,
        );
        ManagerCron::inject($manager, $mutex, self::cronClock());
        $injectedCron = self::managerCron($manager);

        $manager->start(new Boot());

        self::assertSame($injectedCron, self::managerCron($manager));
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
        ManagerCron::inject($manager, $mutex, self::cronClock());
        $manager->start(new Boot());
        $this->controlledEventLoop()->runUntilIdle();
        $manager->stop(new Shutdown());
    }

    private static function cronClock(): FrozenClock
    {
        $clock = FrozenClock::fromUTC();
        $clock->setTo(new DateTimeImmutable('2020-01-01 12:00:00', new DateTimeZone('UTC')));

        return $clock;
    }

    private static function managerCron(Manager $manager): Cron
    {
        $reflection = new ReflectionClass(Manager::class);
        $property   = $reflection->getProperty('cron');
        $property->setAccessible(true);

        $cron = $property->getValue($manager);
        self::assertInstanceOf(Cron::class, $cron);

        return $cron;
    }

    private static function cronScheduler(Cron $cron): Scheduler
    {
        $reflection = new ReflectionClass(Cron::class);
        $property   = $reflection->getProperty('scheduler');
        $property->setAccessible(true);

        $scheduler = $property->getValue($cron);
        self::assertInstanceOf(Scheduler::class, $scheduler);

        return $scheduler;
    }

    /** @return list<ActionInterface> */
    private static function cronActions(Cron $cron): array
    {
        $reflection = new ReflectionClass(Cron::class);
        $property   = $reflection->getProperty('actions');
        $property->setAccessible(true);

        $actions = $property->getValue($cron);
        self::assertIsArray($actions);

        $typedActions = [];
        foreach ($actions as $action) {
            self::assertInstanceOf(ActionInterface::class, $action);
            $typedActions[] = $action;
        }

        return $typedActions;
    }

    private static function isSchedulerActive(Scheduler $scheduler): bool
    {
        $reflection = new ReflectionClass(Scheduler::class);
        $property   = $reflection->getProperty('active');
        $property->setAccessible(true);

        return (bool) $property->getValue($scheduler);
    }
}
