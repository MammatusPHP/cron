<?php

declare(strict_types=1);

namespace Mammatus\Cron\Generated;

use Mammatus\Cron\BuildIn\Noop;
use WyriHaximus\React\Cron;
use WyriHaximus\React\Cron\Action;
use WyriHaximus\React\Mutex\Contracts\MutexInterface;

abstract class AbstractManager
{
    private ?Cron $cron = null;

    final protected function cron(MutexInterface $mutex): Cron
    {
        if ($this->cron instanceof Cron) {
            return $this->cron;
        }

        $this->cron = Cron::createWithMutex(
            $mutex,
            new Action(
                'cron:Mammatus:Cron:BuildIn:Noop:noop',
                120,
                '* * * * *',
                fn () => $this->perform(Noop::class)
            ),
        );

        return $this->cron;
    }

    abstract protected function perform(string $class): void;
}
