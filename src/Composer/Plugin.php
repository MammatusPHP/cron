<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use Mammatus\Cron\Contracts\Action;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;
use WyriHaximus\Twig\SimpleTwig;

use function chmod;
use function file_get_contents;
use function file_put_contents;

final class Plugin implements GenerativePlugin
{
    public static function name(): string
    {
        return 'mammatus/cron';
    }

    public static function log(LogStages $stage): string
    {
        return match ($stage) {
            LogStages::Init => 'Locating actions',
            LogStages::Error => 'An error occurred: %s',
            LogStages::Collected => 'Found %d action(s)',
            LogStages::Completion => 'Generated static abstract action manager and action list in %s second(s)',
        };
    }

    /** @inheritDoc */
    public function filters(): iterable
    {
        yield new ComposerJsonHasItemWithSpecificValue('mammatus.cron.has-actions', true);
        yield new IsInstantiable();
        yield new ImplementsInterface(Action::class);
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield new Collector();
    }

    public function compile(string $rootPath, ItemContract ...$items): void
    {
        $classContentsManager = SimpleTwig::render(
            file_get_contents( /** @phpstan-ignore-line */
                $rootPath . '/etc/generated_templates/AbstractManager.php.twig',
            ),
            ['actions' => $items],
        );
        $installPathManager   = $rootPath . '/src/Generated/AbstractManager.php';
        file_put_contents($installPathManager, $classContentsManager); /** @phpstan-ignore-line */
        chmod($installPathManager, 0664);

        $classContentsList = SimpleTwig::render(
            file_get_contents( /** @phpstan-ignore-line */
                $rootPath . '/etc/generated_templates/AbstractList.php.twig',
            ),
            ['actions' => $items],
        );
        $installPathList   = $rootPath . '/src/Generated/AbstractList.php';
        file_put_contents($installPathList, $classContentsList); /** @phpstan-ignore-line */
        chmod($installPathList, 0664);
    }
}
