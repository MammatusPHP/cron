<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use Mammatus\Cron\Contracts\Action;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

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
            LogStages::Completion => 'Generated static abstract queue manager and queue list in %s second(s)',
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
        $environment = new Environment(
            new ArrayLoader(['swporugifhapwergpfoawepoifjawpejfopiajwepoijfaeipwjfpoiawjeiopfaw' => '{{ include(template_from_string(swporugifhapwergpfoawepoifjawpejfopiajwepoijfaeipwjfpoiawjeiopfaw)) }}']),
        );
        $environment->addExtension(new StringLoaderExtension());
        $environment->addExtension(new SandboxExtension(new SecurityPolicy()));

        $classContentsManager = $environment->render(
            'swporugifhapwergpfoawepoifjawpejfopiajwepoijfaeipwjfpoiawjeiopfaw',
            [
                'swporugifhapwergpfoawepoifjawpejfopiajwepoijfaeipwjfpoiawjeiopfaw' => file_get_contents($rootPath . '/etc/generated_templates/AbstractManager.php.twig'), /** @phpstan-ignore-line */
                'actions' => $items,
            ],
        );
        $installPathManager   = $rootPath . '/src/Generated/AbstractManager.php';
        file_put_contents($installPathManager, $classContentsManager); /** @phpstan-ignore-line */
        chmod($installPathManager, 0664); /** @phpstan-ignore-line */

        $classContentsList = $environment->render(
            'swporugifhapwergpfoawepoifjawpejfopiajwepoijfaeipwjfpoiawjeiopfaw',
            [
                'swporugifhapwergpfoawepoifjawpejfopiajwepoijfaeipwjfpoiawjeiopfaw' => file_get_contents($rootPath . '/etc/generated_templates/AbstractList_.php.twig'), /** @phpstan-ignore-line */
                'actions' => $items,
            ],
        );
        $installPathList   = $rootPath . '/src/Generated/AbstractList_.php';
        file_put_contents($installPathList, $classContentsList); /** @phpstan-ignore-line */
        chmod($installPathList, 0664); /** @phpstan-ignore-line */
    }
}
