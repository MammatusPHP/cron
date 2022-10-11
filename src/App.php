<?php

declare(strict_types=1);

namespace Mammatus\Cron;

use function React\Async\async;
use function React\Async\await;

final class App
{
    private const EXIT_CODE_SUCCESS = 0;
    private const EXIT_CODE_FAILURE = 1;

    public function __construct(
        private readonly Performer $performer,
    ) {
    }

    public function run(string $className): int
    {
        /**
         * @phpstan-ignore-next-line
         * @psalm-suppress TooManyArguments
         */
        return await(async(fn (string $className): int => $this->performer->run($className) === Performer::SUCCESS ? self::EXIT_CODE_SUCCESS : self::EXIT_CODE_FAILURE)($className));
    }
}
