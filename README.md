# cron

Automatically discover, generate, and run cron actions in your Mammatus application.

## Installation

Install the package via Composer:

    composer require mammatus/cron

### Setup

Add the required extra configuration in `composer.json`:

    {
      "extra": {
        "mammatus": {
          "cron": {
            "has-actions": true
          }
        }
      }
    }

## Creating a Cron Action

Create a class that implements `Mammatus\Cron\Contracts\Action` and tag it with the `#[Cron]` attribute:

    <?php

    declare(strict_types=1);

    namespace App\Cron;

    use Mammatus\Cron\Attributes\Cron;
    use Mammatus\Cron\Contracts\Action;

    #[Cron(name: 'my.task', ttl: 60, schedule: '* * * * *')]
    final class MyTask implements Action
    {
        public function perform(): void
        {
            // Your cron logic here
        }
    }

The `#[Cron]` attribute requires three parameters:

- **name** : a unique identifier for the cron job
- **ttl** : the time-to-live lock in seconds; prevents concurrent executions
- **schedule** : a standard cron expression defining when the job runs

## Action Types

Cron actions come in two types:

### Internal (default)

By default, actions run inside the application process using the generated `Mammatus\Cron\Manager`. A mutex-based lock ensures that only one instance of an action runs at a time, respecting the `ttl` value from the `#[Cron]` attribute.

    #[Cron(name: 'no.op', ttl: 69, schedule: '* * * * *')]
    final class Noop implements Action
    {
        public function perform(): void
        {
            // Runs internally via the Manager
        }
    }

### Kubernetes

To offload an action to a Kubernetes CronJob, add the `#[SplitOut]` attribute. The package generates a Helm value entry instead of registering the action with the internal manager.

    <?php

    declare(strict_types=1);

    namespace App\Cron;

    use Mammatus\Cron\Attributes\Cron;
    use Mammatus\Cron\Contracts\Action;
    use Mammatus\Kubernetes\Attributes\SplitOut;

    #[SplitOut]
    #[Cron(name: 'ye.et', ttl: 69, schedule: '* * * * *')]
    final class Yep implements Action
    {
        public function perform(): void
        {
            // Runs as a Kubernetes CronJob
        }
    }

## Generated Artifacts

During `composer install` or `composer update`, the plugin generates two files:

- **`src/Manager.php`** : an in-process cron scheduler built on [wyrihaximus/react-cron](https://github.com/wyrihaximus/reactphp-cron) that registers all Internal actions with mutex-based locking.
- **`src/Kubernetes/Helm/CronJobsValues.php`** : a broadcast listener that provides Helm values for all Kubernetes CronJobs.

Both files are generated from Twig templates in `etc/generated_templates/` and should not be edited manually.

## Running a Single Action via CLI

To run a single cron action outside of the manager, use the provided binary:

    bin/mammatus-cron App\Cron\MyTask

# License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
