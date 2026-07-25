<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Stub;

use BadMethodCallException;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use SplQueue;

use function call_user_func;
use function count;
use function min;
use function spl_object_id;

/**
 * Event loop with virtual time for deterministic, fast async tests.
 */
// phpcs:disable
final class ControlledEventLoop implements LoopInterface
{
    private float $time = 0.0;

    /** @var SplQueue<callable> */
    private SplQueue $futureTickQueue;

    /** @var array<int, array{timer: TimerInterface, scheduled: float, periodic: bool}> */
    private array $timers = [];

    private bool $running = false;

    public function __construct()
    {
        $this->futureTickQueue = new SplQueue();
    }

    public function advance(float $seconds): void
    {
        if ($seconds <= 0.0) {
            $this->tick();

            return;
        }

        $target = $this->time + $seconds;

        while ($this->time < $target) {
            $nextEvent = $this->nextEventTime();

            if ($nextEvent === null || $nextEvent > $target) {
                $this->time = $target;
                $this->tick();

                break;
            }

            $this->time = $nextEvent;
            $this->tick();
        }
    }

    public function runUntilIdle(float $maxAdvance = 300.0): void
    {
        $this->advance($maxAdvance);
    }

    public function addReadStream($stream, $listener): void
    {
        throw new BadMethodCallException('Not supported by ControlledEventLoop');
    }

    public function addWriteStream($stream, $listener): void
    {
        throw new BadMethodCallException('Not supported by ControlledEventLoop');
    }

    public function removeReadStream($stream): void
    {
    }

    public function removeWriteStream($stream): void
    {
    }

    public function addTimer($interval, $callback): TimerInterface
    {
        $timer                               = new ControlledTimer($interval, $callback, false);
        $this->timers[spl_object_id($timer)] = [
            'timer'     => $timer,
            'scheduled' => $this->time + $timer->getInterval(),
            'periodic'  => false,
        ];

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback): TimerInterface
    {
        $timer                               = new ControlledTimer($interval, $callback, true);
        $this->timers[spl_object_id($timer)] = [
            'timer'     => $timer,
            'scheduled' => $this->time + $timer->getInterval(),
            'periodic'  => true,
        ];

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer): void
    {
        unset($this->timers[spl_object_id($timer)]);
    }

    public function futureTick($listener): void
    {
        $this->futureTickQueue->enqueue($listener);
    }

    public function addSignal($signal, $listener): void
    {
        throw new BadMethodCallException('Not supported by ControlledEventLoop');
    }

    public function removeSignal($signal, $listener): void
    {
    }

    public function run(): void
    {
        $this->running = true;

        while ($this->running) {
            if ($this->futureTickQueue->isEmpty() && count($this->timers) === 0) {
                break;
            }

            $nextEvent = $this->nextEventTime();

            if ($nextEvent === null) {
                break;
            }

            $this->time = $nextEvent;
            $this->tick();
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function tick(): void
    {
        do {
            $hadFutureTicks = ! $this->futureTickQueue->isEmpty();

            if ($hadFutureTicks) {
                $this->tickFutureTicks();
            }

            $this->tickTimers();
        } while (! $this->futureTickQueue->isEmpty());
    }

    private function tickFutureTicks(): void
    {
        $count = $this->futureTickQueue->count();

        while ($count-- > 0) {
            call_user_func($this->futureTickQueue->dequeue());
        }
    }

    private function tickTimers(): void
    {
        if (count($this->timers) === 0) {
            return;
        }

        foreach ($this->timers as $id => $scheduledTimer) {
            if ($scheduledTimer['scheduled'] > $this->time) {
                continue;
            }

            $scheduled = $scheduledTimer['scheduled'];

            if (! array_key_exists($id, $this->timers) || $this->timers[$id]['scheduled'] !== $scheduled) {
                continue;
            }

            $timer = $scheduledTimer['timer'];
            call_user_func($timer->getCallback(), $timer);

            if ($timer->isPeriodic() && array_key_exists($id, $this->timers) && $this->timers[$id]['scheduled'] === $scheduled) {
                $this->timers[$id]['scheduled'] = $this->time + $timer->getInterval();

                continue;
            }

            unset($this->timers[$id]);
        }
    }

    private function nextEventTime(): float|null
    {
        $next = null;

        if (! $this->futureTickQueue->isEmpty()) {
            $next = $this->time;
        }

        foreach ($this->timers as $scheduledTimer) {
            $next = $next === null
                ? $scheduledTimer['scheduled']
                : min($next, $scheduledTimer['scheduled']);
        }

        return $next;
    }
}

final class ControlledTimer implements TimerInterface
{
    private float $interval;

    /** @var callable */
    private $callback;

    private bool $periodic;

    public function __construct(float|int $interval, callable $callback, bool $periodic)
    {
        if ($interval < 0.000001) {
            $interval = 0.000001;
        }

        $this->interval = (float) $interval;
        $this->callback = $callback;
        $this->periodic = $periodic;
    }

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function isPeriodic(): bool
    {
        return $this->periodic;
    }
}
