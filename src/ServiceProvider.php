<?php

namespace NickDeKruijk\LeapTemplate;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use NickDeKruijk\LeapTemplate\Commands\ContentCommand;
use NickDeKruijk\LeapTemplate\Commands\ContentDeleteCommand;
use NickDeKruijk\LeapTemplate\Commands\TemplateCommand;

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
                ContentDeleteCommand::class,
            ]);
        }
    }
}
