<?php

declare(strict_types=1);

namespace Mammatus\Cron\PHPSan;

use Mammatus\Cron\Contracts\Action;
use Override;
use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

final class ShipMonkDeadCode extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    public function shouldMarkMethodAsUsed(ReflectionMethod $method): VirtualUsageData|null
    {
        if ($method->getDeclaringClass()->implementsInterface(Action::class)) {
            return VirtualUsageData::withNote('Class is a Cron Action');
        }

        return null;
    }
}
