<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use Mammatus\Cron\Attributes\Cron;
use Mammatus\Kubernetes\Attributes\SplitOut;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ItemCollector;

use function array_key_exists;

final class Collector implements ItemCollector
{
    /** @return iterable<ItemContract> */
    public function collect(ReflectionClass $class): iterable
    {
        /** @var array<Cron> $attributes */
        $attributes = [];
        foreach (new \ReflectionClass($class->getName())->getAttributes() as $attributeReflection) {
            $attribute                     = $attributeReflection->newInstance();
            $attributes[$attribute::class] = $attribute;
        }

        if (! array_key_exists(Cron::class, $attributes)) {
            return;
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        yield new Item(
            $class->getName(),
            $attributes[Cron::class], /** @phpstan-ignore-line */
            array_key_exists(SplitOut::class, $attributes),
        );
    }
}
