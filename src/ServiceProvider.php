<?php

namespace NickDeKruijk\LeapTemplate;

use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use NickDeKruijk\LeapTemplate\Commands\ContentCommand;
use NickDeKruijk\LeapTemplate\Commands\TemplateCommand;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Dev-only scaffolding for the nickdekruijk/leap frontend template. This package is
 * meant to be installed with `composer require --dev`, so on production
 * (`composer install --no-dev`) it — and its commands — are absent entirely.
 *
 * The runtime it scaffolds (the admin panel, the page/content models it copies in) all
 * live in nickdekruijk/leap, which must be a normal (non-dev) requirement of the host.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TemplateCommand::class,
                ContentCommand::class,
            ]);

            $this->warnIfNotDev();
        }
    }

    /**
     * This is dev-only tooling; nudge if it was required non-dev (it would then ship to
     * production for no reason). Skipped while developing this package itself.
     */
    protected function warnIfNotDev(): void
    {
        $self = 'nickdekruijk/leap-template';

        if (! class_exists(InstalledVersions::class) || InstalledVersions::getRootPackage()['name'] === $self) {
            return;
        }

        if (! InstalledVersions::isDevRequirement($self)) {
            (new ConsoleOutput)->getErrorOutput()->writeln(
                '<comment>nickdekruijk/leap-template is dev-only tooling — install it with `composer require --dev` so it does not ship to production.</comment>'
            );
        }
    }
}
