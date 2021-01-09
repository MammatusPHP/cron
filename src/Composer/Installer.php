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
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use function ApiClients\Tools\Rx\observableFromArray;
use function array_key_exists;
use function dirname;
use function file_exists;
use function microtime;
use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
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
            foreach ($composer->getPackage()->getAutoload()['psr-4'] as $ns => $p) {
                $p = dirname($composer->getConfig()->get('vendor-dir')) . '/' . $p;
                spl_autoload_register(static function ($class) use ($ns, $p) {
                    if (strpos($class, $ns) === 0) {
                        $fileName = $p . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($ns))) . '.php';
                        if (file_exists($fileName)) {
                            echo  $fileName;
                            include $fileName;
                        }
                    }
                });
            }
        }

        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/iterator-or-array-to-array/src/functions_include.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/list-classes-in-directory/src/functions_include.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/string-get-in/src/functions_include.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/constants/src/Numeric/constants_include.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/igorw/get-in/src/get_in.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/jetbrains/phpstorm-stubs/PhpStormStubsMap.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/filesystem.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/strings.php';
        /** @psalm-suppress UnresolvableInclude */
        require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/simple-twig/src/functions_include.php';
        /** @psalm-suppress UnresolvableInclude */

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
            return dirname($composerConfig->get('vendor-dir'));
        }

        return $composerConfig->get('vendor-dir') . '/mammatus/cron';
    }

    private static function findAllActions(Composer $composer, IOInterface $io): array
    {
        $annotationReader = new AnnotationReader();
        $vendorDir = $composer->getConfig()->get('vendor-dir');

        retry:
        try {
            $classReflector = new ClassReflector(
                (new MakeLocatorForComposerJsonAndInstalledJson())(dirname($vendorDir), (new BetterReflection())->astLocator()),
            );
        } catch (InvalidPrefixMapping $invalidPrefixMapping) {
            mkdir(explode('" is not a', explode('" for prefix "', $invalidPrefixMapping->getMessage())[1])[0]);
            goto retry;
        }


        $packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packages[] = $composer->getPackage();

        return (new Collection($packages))->filter(static function (PackageInterface $package): bool {
            return count($package->getAutoload()) > 0;
        })->filter(static function (PackageInterface $package): bool {
            return getIn($package->getExtra(), 'mammatus.cron.has-actions', false);
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
        })->map(static function (string $path): string {
            return rtrim($path, '/');
        })->filter(static function (string $path): bool {
            return file_exists($path);
        })->flatMap(static function (string $path): array {
            return
                iteratorOrArrayToArray(
                    listClassesInDirectories($path)
                );
        })->flatMap(static function (string $class) use ($classReflector, $io): array {
            try {
                /** @psalm-suppress PossiblyUndefinedVariable */
                return [
                    (static function (ReflectionClass $reflectionClass): ReflectionClass {
                        $reflectionClass->getInterfaces();
                        $reflectionClass->getMethods();

                        return $reflectionClass;
                    })($classReflector->reflect($class)),
                ];
            } catch (IdentifierNotFound $identifierNotFound) {
                $io->write(sprintf(
                    '<info>mammatus/cron:</info> Error while reflecting "<fg=cyan>%s</>": <fg=yellow>%s</>',
                    $class,
                    $identifierNotFound->getMessage()
                ));
            }

            return [];
        })->filter(static function (ReflectionClass $class): bool {
            return $class->isInstantiable();
        })->filter(static function (ReflectionClass $class): bool {
            return $class->implementsInterface(Action::class);
        })->flatMap(static function (ReflectionClass $class) use ($annotationReader): array {
            $annotations = [];
            foreach ($annotationReader->getClassAnnotations(new \ReflectionClass($class->getName())) as $annotation) {
                $annotations[get_class($annotation)] = $annotation;
            }

            return [
                [
                    'class' => $class->getName(),
                    'annotations' => $annotations,
                ],
            ];
        })->filter(static function (array $classNAnnotations): bool {
            if (!array_key_exists(Cron::class, $classNAnnotations['annotations'])) {
                return false;
            }

            return true;
        })->flatMap(static function (array $classNAnnotations): array {
            return [[
                'class' => $classNAnnotations['class'],
                'cron' => $classNAnnotations['annotations'][Cron::class],
            ]];
        })->toArray();
    }
}
