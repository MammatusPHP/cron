<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron;

use Mammatus\Cron\BuildIn\Noop;
use Mammatus\Cron\Manager;
use Mammatus\Cron\Performer;
use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Mutex\Memory;

use function React\Async\await;
use function React\Promise\Timer\sleep;
use function Safe\date;

final class ManagerTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function startRunStop(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug('Starting cron manager')->shouldBeCalled();
        $logger->debug('Started cron manager')->shouldBeCalled();
        $logger->log('debug', 'Getting job', ['cronjob' => 'Mammatus\Cron\BuildIn\Noop'])->shouldBeCalled();
        $logger->log('debug', 'Starting job', ['cronjob' => 'Mammatus\Cron\BuildIn\Noop'])->shouldBeCalled();
        $logger->log('debug', 'Job finished', ['cronjob' => 'Mammatus\Cron\BuildIn\Noop'])->shouldBeCalled();
        $logger->debug('Stopping cron manager')->shouldBeCalled();
        $logger->debug('Stopped cron manager')->shouldBeCalled();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(Noop::class)->shouldBeCalled()->willReturn(new Noop());

        $manager = new Manager(
            $logger->reveal(),
            new Memory(),
            new Performer(
                $container->reveal(),
                $logger->reveal()
            )
        );

        $manager->start(new Initialize());
        await(sleep(65 - (int) date('s')));
        $manager->stop(new Shutdown());
    }
}
