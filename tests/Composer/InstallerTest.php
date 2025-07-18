<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mammatus\Cron\Composer\Installer;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\StreamOutput;
use WyriHaximus\TestUtilities\TestCase;

use function closedir;
use function dirname;
use function file_exists;
use function fseek;
use function in_array;
use function is_dir;
use function is_file;
use function readdir;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\fileperms;
use function Safe\fopen;
use function Safe\mkdir;
use function Safe\opendir;
use function Safe\stream_get_contents;
use function Safe\unlink;
use function sprintf;
use function substr;

use const DIRECTORY_SEPARATOR;

final class InstallerTest extends TestCase
{
    #[Test]
    public function getSubscribedEvents(): void
    {
        self::assertSame([ScriptEvents::PRE_AUTOLOAD_DUMP => 'findActions'], Installer::getSubscribedEvents());
    }

    #[Test]
    public function generate(): void
    {
        $composerConfig = new Config();
        $composerConfig->merge([
            'config' => [
                'vendor-dir' => $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR,
            ],
        ]);
        $rootPackage = new RootPackage('mammatus/cron', 'dev-master', 'dev-master');
        $rootPackage->setExtra([
            'mammatus' => [
                'cron' => ['has-actions' => true],
            ],
        ]);
        $rootPackage->setAutoload([
            'psr-4' => ['Mammatus\\Cron\\' => 'src'],
        ]);

        $io         = new class () extends NullIO {
            private readonly StreamOutput $output;

            public function __construct()
            {
                $this->output = new StreamOutput(fopen('php://memory', 'rw'), decorated: false);
            }

            public function output(): string
            {
                fseek($this->output->getStream(), 0);

                return stream_get_contents($this->output->getStream());
            }

            /** @inheritDoc */
            public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
            {
                $this->output->write($messages, $newline, $verbosity & StreamOutput::OUTPUT_RAW);
            }
        };
        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages()->andReturn([]);
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);
        $event = new Event(
            ScriptEvents::PRE_AUTOLOAD_DUMP,
            $composer,
            $io,
        );

        $installer = new Installer();

        // Test dead methods and make Infection happy
        $installer->activate($composer, $io);
        $installer->deactivate($composer, $io);
        $installer->uninstall($composer, $io);

        $this->recurseCopy(dirname(__DIR__, 2) . '/', $this->getTmpDir());

        $fileNameList = $this->getTmpDir() . 'src/Generated/AbstractList.php';
        if (file_exists($fileNameList)) { /** @phpstan-ignore-line */
            unlink($fileNameList);
        }

        $fileNameManager = $this->getTmpDir() . 'src/Generated/AbstractManager.php';
        if (file_exists($fileNameManager)) { /** @phpstan-ignore-line */
            unlink($fileNameManager);
        }

        self::assertFileDoesNotExist($fileNameList);
        self::assertFileDoesNotExist($fileNameManager);

        // Do the actual generating
        Installer::findActions($event);

        $output = $io->output();

        self::assertStringContainsString('<info>mammatus/cron:</info> Locating actions', $output);
        self::assertStringContainsString('<info>mammatus/cron:</info> Generated static abstract queue manager and queue list in ', $output);
        self::assertStringContainsString('<info>mammatus/cron:</info> Found 1 action(s)', $output);
//        self::assertStringContainsString('<error>mammatus/cron:</error> An error occurred:  Cannot reflect "<fg=cyan>Mammatus\Cron\Manager</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "Mammatus\Cron\Generated\AbstractManager" could not be found in the located source</>', $output);

        self::assertFileExists($fileNameList);
        self::assertFileExists($fileNameManager);
        self::assertTrue(in_array(
            substr(sprintf('%o', fileperms($fileNameList)), -4),
            [
                '0664',
                '0666',
            ],
            true,
        ));
        self::assertTrue(in_array(
            substr(sprintf('%o', fileperms($fileNameManager)), -4),
            [
                '0664',
                '0666',
            ],
            true,
        ));
        $fileContentsList = file_get_contents($fileNameList);
        self::assertStringContainsStringIgnoringCase(' * @see \Mammatus\Cron\BuildIn\Noop', $fileContentsList);
        self::assertStringContainsStringIgnoringCase('yield \'internal-no.op-Mammatus-Cron-BuildIn-Noop\' => new Action(', $fileContentsList);
        self::assertStringContainsStringIgnoringCase('addOns: \json_decode(\'[]\', true),', $fileContentsList);
        $fileContentsManager = file_get_contents($fileNameManager);
        self::assertStringContainsStringIgnoringCase('/** @see \Mammatus\Cron\BuildIn\Noop */', $fileContentsManager);
        self::assertStringContainsStringIgnoringCase('new Action(', $fileContentsManager);
        self::assertStringContainsStringIgnoringCase('fn () => $this->perform(\Mammatus\Cron\BuildIn\Noop::class),', $fileContentsManager);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (! file_exists($dst)) { /** @phpstan-ignore-line */
            mkdir($dst);
        }

        while (( $file = readdir($dir)) !== false) {
            if (( $file === '.' ) || ( $file === '..' )) {
                continue;
            }

            if (is_dir($src . '/' . $file)) { /** @phpstan-ignore-line */
                $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } elseif (is_file($src . '/' . $file)) { /** @phpstan-ignore-line */
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }

        closedir($dir);
    }
}
