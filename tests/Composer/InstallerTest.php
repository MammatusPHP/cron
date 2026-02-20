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
use Mammatus\Cron\Composer\CodeGenerator;
use Mammatus\DevApp\Cron\Noop;
use Mammatus\DevApp\Cron\Yep;
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
use function sprintf;
use function substr;
use function touch;

use const DIRECTORY_SEPARATOR;

final class InstallerTest extends TestCase
{
    #[Test]
    public function getSubscribedEvents(): void
    {
        self::assertSame([ScriptEvents::PRE_AUTOLOAD_DUMP => 'findActions'], CodeGenerator::getSubscribedEvents());
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
            'psr-4' => [
                'Mammatus\\Cron\\' => 'src',
                'Mammatus\\DevApp\\Cron\\' => 'etc/dev-app',
            ],
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

            /**
             * @inheritDoc
             * @phpstan-ignore typeCoverage.paramTypeCoverage
             */
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

        $installer = new CodeGenerator();

        // Test dead methods and make Infection happy
        $installer->activate($composer, $io);
        $installer->deactivate($composer, $io);
        $installer->uninstall($composer, $io);

        $this->recurseCopy(dirname(__DIR__, 2) . '/', $this->getTmpDir());

        $fileNameCJV     = $this->getTmpDir() . 'src/Generated/Kubernetes/Helm/CronJobsValues.php';
        $fileNameManager = $this->getTmpDir() . 'src/Generated/Manager.php';
        $sneakyFile      = $this->getTmpDir() . 'src' . DIRECTORY_SEPARATOR . 'Generated' . DIRECTORY_SEPARATOR . 'sneaky.file';
        touch($sneakyFile);

        self::assertFileExists($sneakyFile);

        // Do the actual generating
        CodeGenerator::findActions($event);

        self::assertFileDoesNotExist($sneakyFile);

        $output = $io->output();

        self::assertStringContainsString('<info>mammatus/cron:</info> Locating actions', $output);
        self::assertStringContainsString('<info>mammatus/cron:</info> Generated static abstract action manager and action list in ', $output);
        self::assertStringContainsString('<info>mammatus/cron:</info> Found 2 action(s)', $output);
        //self::assertStringContainsString('<error>mammatus/cron:</error> An error occurred:  Cannot reflect "<fg=cyan>Mammatus\Cron\Manager</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "Mammatus\Cron\Generated\AbstractManager" could not be found in the located source</>', $output);

        self::assertFileExists($fileNameCJV);
        self::assertFileExists($fileNameManager);

        self::assertTrue(in_array(
            substr(sprintf('%o', fileperms($fileNameCJV)), -4),
            [
                '0764',
                '0664',
                '0666',
            ],
            true,
        ));
        self::assertTrue(in_array(
            substr(sprintf('%o', fileperms($fileNameManager)), -4),
            [
                '0764',
                '0664',
                '0666',
            ],
            true,
        ));

        $fileContentsCJV = file_get_contents($fileNameCJV);
        self::assertStringContainsStringIgnoringCase('\\' . Yep::class . '::class,', $fileContentsCJV);
        self::assertStringContainsStringIgnoringCase('\'cron-ye-et\',', $fileContentsCJV);
        self::assertStringContainsStringIgnoringCase('\json_decode(\'[]\', true),', $fileContentsCJV);
        self::assertStringNotContainsStringIgnoringCase('cron-no-op', $fileContentsCJV);
        self::assertStringNotContainsStringIgnoringCase('fn () => $this->perform(\\' . Noop::class . '::class),', $fileContentsCJV);

        $fileContentsManager = file_get_contents($fileNameManager);
        self::assertStringContainsStringIgnoringCase('* @see \\' . Noop::class . ' */', $fileContentsManager);
        self::assertStringContainsStringIgnoringCase('new Cron\Action(', $fileContentsManager);
        self::assertStringContainsStringIgnoringCase('fn () => $this->perform(\\' . Noop::class . '::class),', $fileContentsManager);
        self::assertStringContainsStringIgnoringCase('cron_no.op', $fileContentsManager);
        self::assertStringNotContainsStringIgnoringCase('cron_ye.et', $fileContentsManager);
        self::assertStringNotContainsStringIgnoringCase('fn () => $this->perform(\\' . Yep::class . '::class),', $fileContentsManager);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.fileExists */
        if (! file_exists($dst)) {
            mkdir($dst);
        }

        while (( $file = readdir($dir)) !== false) {
            if (( $file === '.' ) || ( $file === '..' )) {
                continue;
            }

            /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.isDir */
            if (is_dir($src . $file)) {
                $this->recurseCopy($src . $file . DIRECTORY_SEPARATOR, $dst . $file . DIRECTORY_SEPARATOR);
                /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.isFile */
            } elseif (is_file($src . $file)) {
                copy($src . $file, $dst . $file);
            }
        }

        closedir($dir);
    }
}
