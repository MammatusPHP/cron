<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use WyriHaximus\TestUtilities\RectorConfig;

return RectorConfig::configure(dirname(__DIR__, 2))->withSkip([
    UnusedForeachValueToArrayKeysRector::class,
])->withPaths([
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dev-app',
]);
