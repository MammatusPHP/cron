<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Support\Collection;
use Mammatus\Cron\Attributes\Cron;
use Mammatus\Cron\Contracts\Action;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use RuntimeException;

use function array_key_exists;
use function count;
use function defined;
use function dirname;
use function explode;
use function file_exists;
use function function_exists;
use function gettype;
use function is_array;
use function is_string;
use function microtime;
use function round;
use function rtrim;
use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\spl_autoload_register;
use function Safe\sprintf;
use function Safe\substr;
use function Safe\unlink;
use function str_replace;
use function strlen;
use function strpos;
use function WyriHaximus\getIn;
use function WyriHaximus\iteratorOrArrayToArray;
use function WyriHaximus\listClassesInDirectories;
use function WyriHaximus\Twig\render;

use const DIRECTORY_SEPARATOR;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => 'findActions'];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    /**
     * Called before every dump autoload, generates a fresh PHP class.
     */
    public static function findActions(Event $event): void
    {
        $start    = microtime(true);
        $io       = $event->getIO();
        $composer = $event->getComposer();

        // Composer is bugged and doesn't handle root package autoloading properly yet
        if (array_key_exists('psr-4', $composer->getPackage()->getAutoload())) {
            /**
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            foreach ($composer->getPackage()->getAutoload()['psr-4'] as $ns => $p) {
                $vendorDir = $composer->getConfig()->get('vendor-dir');
                if (! is_string($vendorDir)) {
                    continue;
                }

                foreach (is_array($p) ? $p : [$p] as $pp) {
                    $pppp = dirname($vendorDir) . '/' . $pp;
                    spl_autoload_register(static function ($class) use ($ns, $pppp): void {
                        if (strpos($class, $ns) !== 0) {
                            return;
                        }

                        $fileName = $pppp . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($ns))) . '.php';
                        if (! file_exists($fileName)) {
                            return;
                        }

                        include $fileName;
                    });
                }
            }
        }

        if (! function_exists('WyriHaximus\iteratorOrArrayToArray')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/iterator-or-array-to-array/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\listClassesInDirectories')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/list-classes-in-directory/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\getIn')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/string-get-in/src/functions_include.php';
        }

        if (! defined('WyriHaximus\Constants\Numeric\ONE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/constants/src/Numeric/constants_include.php';
        }

        if (! defined('WyriHaximus\Constants\Boolean\TRUE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/constants/src/Boolean/constants_include.php';
        }

        if (! function_exists('igorw\get_in')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/igorw/get-in/src/get_in.php';
        }

        if (! function_exists('Safe\file_get_contents')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/filesystem.php';
        }

        if (! function_exists('Safe\sprintf')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/strings.php';
        }

        if (! function_exists('WyriHaximus\Twig\render')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/simple-twig/src/functions_include.php';
        }

        $io->write('<info>mammatus/cron:</info> Locating actions');

        $actions = self::findAllActions($composer, $io);

        $io->write(sprintf('<info>mammatus/cron:</info> Found %s action(s)', count($actions)));

        $classContents = render(
            file_get_contents(
                self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage()) . '/etc/generated_templates/AbstractManager.php.twig'
            ),
            ['actions' => $actions]
        );

        $installPath = self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage())
            . '/src/Generated/AbstractManager.php';

        if (file_exists($installPath)) {
            unlink($installPath);
        }

        file_put_contents($installPath, $classContents);
        chmod($installPath, 0664);

        $io->write(sprintf(
            '<info>mammatus/cron:</info> Generated static abstract cron manager in %s second(s)',
            round(microtime(true) - $start, 2)
        ));
    }

    /**
     * Find the location where to put the generate PHP class in.
     */
    private static function locateRootPackageInstallPath(
        Config $composerConfig,
        RootPackageInterface $rootPackage
    ): string {
        // You're on your own
        if ($rootPackage->getName() === 'mammatus/cron') {
            $vendorDir = $composerConfig->get('vendor-dir');
            if (! is_string($vendorDir)) {
                throw new RuntimeException('Expected string, got ' . gettype($vendorDir));
            }

            return dirname($vendorDir);
        }

        return $composerConfig->get('vendor-dir') . '/mammatus/cron';
    }

    /**
     * @return array<mixed>
     */
    private static function findAllActions(Composer $composer, IOInterface $io): array
    {
        $annotationReader = new AnnotationReader();
        $vendorDir        = $composer->getConfig()->get('vendor-dir');
        if (! is_string($vendorDir)) {
            return [];
        }

        retry:
        try {
            $classReflector = new DefaultReflector(
                (new MakeLocatorForComposerJsonAndInstalledJson())(dirname($vendorDir), (new BetterReflection())->astLocator()),
            );
        } catch (InvalidPrefixMapping $invalidPrefixMapping) {
            mkdir(explode('" is not a', explode('" for prefix "', $invalidPrefixMapping->getMessage())[1])[0]);
            goto retry;
        }

        $packages   = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packages[] = $composer->getPackage();

        return (new Collection($packages))->filter(static function (PackageInterface $package): bool {
            return count($package->getAutoload()) > 0;
        })->filter(static function (PackageInterface $package): bool {
            return (bool) getIn($package->getExtra(), 'mammatus.cron.has-actions', false);
        })->filter(static function (PackageInterface $package): bool {
            return array_key_exists('classmap', $package->getAutoload()) || array_key_exists('psr-4', $package->getAutoload());
        })->flatMap(static function (PackageInterface $package) use ($vendorDir): array {
            $packageName = $package->getName();
            $autoload    = $package->getAutoload();
            $paths       = [];
            foreach (['classmap', 'psr-4'] as $item) {
                if (! array_key_exists($item, $autoload)) {
                    continue;
                }

                foreach ($autoload[$item] as $path) {
                    if (is_string($path)) {
                        if ($package instanceof RootPackageInterface) {
                            $paths[] = dirname($vendorDir) . DIRECTORY_SEPARATOR . $path;
                        } else {
                            $paths[] = $vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $path;
                        }
                    }

                    if (! is_array($path)) {
                        continue;
                    }

                    foreach ($path as $p) {
                        if ($package instanceof RootPackageInterface) {
                            $paths[] = dirname($vendorDir) . DIRECTORY_SEPARATOR . $p;
                        } else {
                            $paths[] = $vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $p;
                        }
                    }
                }
            }

            return $paths;
        })->map( /** @phpstan-ignore-next-line */
            static fn (string $path): string => rtrim($path, '/')
        )->filter(static function (string $path): bool {
            return file_exists($path);
        })->flatMap(static function (string $path): array {
            return iteratorOrArrayToArray(
                listClassesInDirectories($path)
            );
        })->flatMap(
            /** @phpstan-ignore-next-line */
            static function (string $class) use ($classReflector, $io): array {
                try {
                    /** @psalm-suppress PossiblyUndefinedVariable */
                    return [
                        (static function (ReflectionClass $reflectionClass): ReflectionClass {
                            $reflectionClass->getInterfaces();
                            $reflectionClass->getMethods();

                            return $reflectionClass;
                        })($classReflector->reflectClass($class)),
                    ];
                } catch (IdentifierNotFound $identifierNotFound) {
                    $io->write(sprintf(
                        '<info>mammatus/cron:</info> Error while reflecting "<fg=cyan>%s</>": <fg=yellow>%s</>',
                        $class,
                        $identifierNotFound->getMessage()
                    ));
                }

                return [];
            }
        )->filter( /** @phpstan-ignore-next-line */
            static fn (ReflectionClass $class): bool => $class->isInstantiable()
        )->filter( /** @phpstan-ignore-next-line */
            static fn (ReflectionClass $class): bool => $class->implementsInterface(Action::class)
        )->flatMap( /** @phpstan-ignore-next-line */
            static function (ReflectionClass $class) use ($annotationReader): array {
                $annotations = [];
                foreach ($annotationReader->getClassAnnotations(new \ReflectionClass($class->getName())) as $annotation) {
                    $annotations[$annotation::class] = $annotation;
                }

                return [
                    [
                        'class' => $class->getName(),
                        'annotations' => $annotations,
                    ],
                ];
            }
        )->filter( /** @phpstan-ignore-next-line */
            static fn (array $classNAnnotations): bool => array_key_exists(Cron::class, $classNAnnotations['annotations'])
        )->flatMap( /** @phpstan-ignore-next-line */
            static fn (array $classNAnnotations): array => [
                [
                    'class' => $classNAnnotations['class'],
                    'cron' => $classNAnnotations['annotations'][Cron::class],
                ],
            ]
        )->toArray();
    }
}
