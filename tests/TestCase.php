<?php

namespace NickDeKruijk\LeapTemplate\Tests;

use NickDeKruijk\LeapTemplate\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Only this package's provider is needed — the scaffolding commands are plain
     * console commands. The tests that touch leap (e.g. its shipped config) resolve it
     * by file path via reflection, without booting the admin runtime.
     */
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
