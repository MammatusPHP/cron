<?php

declare(strict_types=1);

namespace Mammatus\Cron\Action;

enum Type: String
{
    // uses Kubernetes Cron Jobs
    case Kubernetes = 'kubernetes';

    // Runs a group process but with shared mutex
    case Internal = 'internal';
}
