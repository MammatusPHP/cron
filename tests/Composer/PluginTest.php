<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Composer;

use Mammatus\Cron\Composer\Plugin;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use function file_put_contents;
use function mkdir;

final class PluginTest extends TestCase
{
    #[Test]
    public function compileRemovesManagerWithoutInternalActions(): void
    {
        $rootPath    = $this->getTmpDir();
        $managerPath = $rootPath . 'src/Manager.php';

        /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.mkdir */
        mkdir($rootPath . 'src', 0777, true);
        /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.filePutContents */
        file_put_contents($managerPath, '<?php // stale manager');

        (new Plugin())->compile($rootPath);

        self::assertFileDoesNotExist($managerPath);
    }
}
