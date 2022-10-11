<?php

declare(strict_types=1);

namespace Mammatus\Tests\Cron\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mammatus\Cron\Composer\Installer;
use Prophecy\Argument;
use WyriHaximus\TestUtilities\TestCase;

use function closedir;
use function dirname;
use function file_exists;
use function fileperms;
use function is_dir;
use function readdir;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\mkdir;
use function Safe\opendir;
use function Safe\sprintf;
use function Safe\substr;

use const DIRECTORY_SEPARATOR;

final class InstallerTest extends TestCase
{
    /**
     * @test
     */
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
            'classmap' => ['dummy/event','dummy/listener/Listener.php'],
            'psr-4' => ['WyriHaximus\\Broadcast\\' => 'src'],
        ]);
        $io = $this->prophesize(IOInterface::class);
        $io->debug('Checked CA file /etc/pki/tls/certs/ca-bundle.crt does not exist or it is not a file.')->shouldBeCalled();
        $io->debug('Checked directory /etc/pki/tls/certs/ca-bundle.crt does not exist or it is not a directory.')->shouldBeCalled();
        $io->debug('Checked CA file /etc/ssl/certs/ca-certificates.crt: valid')->shouldBeCalled();
        $io->write('<info>mammatus/cron:</info> Locating actions')->shouldBeCalled();
        $io->write('<info>mammatus/cron:</info> Found 1 action(s)')->shouldBeCalled();
        $io->write(Argument::containingString('<info>mammatus/cron:</info> Generated static abstract cron manager in '))->shouldBeCalled();
        $io->write(Argument::containingString('<info>mammatus/cron:</info> Generated static abstract cron manager in -'))->shouldNotBeCalled();

        $repository        = $this->prophesize(InstalledRepositoryInterface::class);
        $repositoryManager = new RepositoryManager($io->reveal(), $composerConfig, Factory::createHttpDownloader($io->reveal(), $composerConfig));
        $repositoryManager->setLocalRepository($repository->reveal());
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);
        $event = new Event(
            ScriptEvents::PRE_AUTOLOAD_DUMP,
            $composer,
            $io->reveal()
        );

        $installer = new Installer();

        // Test dead methods and make Infection happy
        $installer->activate($composer, $io->reveal());
        $installer->deactivate($composer, $io->reveal());
        $installer->uninstall($composer, $io->reveal());

        $this->recurseCopy(dirname(dirname(__DIR__)) . '/', $this->getTmpDir());

        $fileName = $this->getTmpDir() . 'src/Generated/AbstractManager.php';

        // Do the actual generating
        Installer::findActions($event);

        self::assertFileExists($fileName);
        self::assertSame('0664', substr(sprintf('%o', fileperms($fileName)), -4));
        $fileContents = file_get_contents($fileName);
        self::assertStringContainsString('new Action(', $fileContents);
        self::assertStringContainsString('\'cron:Mammatus:Cron:BuildIn:Noop:noop\',', $fileContents);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (! file_exists($dst)) {
            mkdir($dst);
        }

        while (( $file = readdir($dir)) !== false) {
            if (( $file === '.' ) || ( $file === '..' )) {
                continue;
            }

            if (is_dir($src . '/' . $file)) {
                $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }

        closedir($dir);
    }
}
