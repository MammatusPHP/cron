<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use WyriHaximus\RectorPHP\RectorConfig;

return RectorConfig::configure(dirname(__DIR__, 2))->withSkip([
    UnusedForeachValueToArrayKeysRector::class,
])->withPaths([
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dev-app',
]);
