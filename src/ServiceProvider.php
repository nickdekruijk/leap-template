<?php

namespace NickDeKruijk\LeapTemplate;

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
     * production for no reason). Reads composer.lock rather than the Composer runtime API
     * (Composer\InstalledVersions::isDevRequirement() only exists on Composer 2.2+) so it
     * works on every Composer version. Skipped while developing this package itself (then
     * it isn't listed in its own lock file).
     */
    protected function warnIfNotDev(): void
    {
        if ($this->packageIsDev('nickdekruijk/leap-template') === false) {
            (new ConsoleOutput)->getErrorOutput()->writeln(
                '<comment>nickdekruijk/leap-template is dev-only tooling — install it with `composer require --dev` so it does not ship to production.</comment>'
            );
        }
    }

    /**
     * Whether a Composer package is installed as a dev-only dependency, read from the
     * host's composer.lock (packages vs packages-dev). Returns true (dev), false (non-dev),
     * or null when the lock file or the package is absent.
     */
    protected function packageIsDev(string $package): ?bool
    {
        $lock = base_path('composer.lock');

        if (! is_file($lock)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($lock), true);

        if (! is_array($data)) {
            return null;
        }

        foreach ($data['packages'] ?? [] as $installed) {
            if (($installed['name'] ?? null) === $package) {
                return false;
            }
        }

        foreach ($data['packages-dev'] ?? [] as $installed) {
            if (($installed['name'] ?? null) === $package) {
                return true;
            }
        }

        return null;
    }
}
