<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use Mammatus\Cron\Action\Type;
use Mammatus\Cron\Contracts\Action;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\Remove;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFile;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

use function array_filter;
use function count;

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
        Remove::directoryContents($rootPath . '/src/Generated');

        /** @phpstan-ignore argument.type */
        $internalActions = array_filter($items, static fn (Item $item): bool => $item->type === Type::Internal);
        if (count($internalActions) > 0) {
            TwigFile::render(
                $rootPath . '/etc/generated_templates/Manager.php.twig',
                $rootPath . '/src/Generated/Manager.php',
                ['actions' => $internalActions],
            );
        }

        TwigFile::render(
            $rootPath . '/etc/generated_templates/AbstractList.php.twig',
            $rootPath . '/src/Generated/AbstractList.php',
            ['actions' => $items],
        );
    }
}
