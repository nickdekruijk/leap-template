<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\Concerns\BuildsTempApp;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * A re-run copies the template's bare lang/<code>.json back over the file
 * laravel-lang had merged its own keys into, so those keys have to be put back.
 * Which instruction that produces depends on two independent things: whether
 * laravel-lang is installed, and whether the locale was ever published. The
 * happy re-run is covered by TemplateInstallTest; these are the other corners,
 * where getting it wrong leaves a site with English validation errors and
 * nothing said about it.
 */
class FrameworkTranslationRepairTest extends TestCase
{
    use BuildsTempApp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTempApp();
    }

    protected function tearDown(): void
    {
        $this->removeTempApp();

        parent::tearDown();
    }

    /**
     * The stub the installer writes. A locale whose lang/<code>.json is byte-identical
     * to it has lost whatever laravel-lang merged in.
     */
    private function installBareStub(string $code): void
    {
        @mkdir($this->temp.'/lang', 0777, true);
        copy(
            dirname(__DIR__, 2).'/stubs/template/lang/'.$code.'.json',
            $this->temp.'/lang/'.$code.'.json',
        );
    }

    private function markAsPublished(string $code): void
    {
        mkdir($this->temp.'/lang/'.$code, 0777, true);
        file_put_contents($this->temp.'/lang/'.$code.'/validation.php', "<?php\n\nreturn [];\n");
    }

    private function installLaravelLang(): void
    {
        mkdir($this->temp.'/vendor/laravel-lang/common', 0777, true);
    }

    /**
     * Stale and never published, with the package missing too: both halves are one
     * job, so the locale is named once in a single require-and-add line rather than
     * split over a require for one reason and an add for another.
     */
    public function test_a_stale_locale_without_the_package_is_folded_into_the_install_line(): void
    {
        $this->installBareStub('nl');

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])
            ->expectsOutputToContain('composer require --dev laravel-lang/common && php artisan lang:add nl')
            ->doesntExpectOutputToContain('Merge Laravel\'s own translations back with')
            ->assertExitCode(0);
    }

    /**
     * Published but stale, and the package is gone: it cannot be repaired with
     * lang:add alone, so the locale falls back in with the ones that still need
     * installing rather than being silently skipped.
     */
    public function test_a_published_but_stale_locale_needs_the_package_back(): void
    {
        $this->markAsPublished('nl');
        $this->installBareStub('nl');

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])
            ->expectsOutputToContain('composer require --dev laravel-lang/common && php artisan lang:add nl')
            ->assertExitCode(0);
    }

    /**
     * Two locales in different states: one never published, one published but
     * stale. Both end up in the same lang:add, once each.
     */
    public function test_a_missing_and_a_stale_locale_are_named_together_once(): void
    {
        $this->markAsPublished('nl');
        $this->installBareStub('nl');
        $this->installLaravelLang();

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl,de',
        ])
            // de is missing entirely, nl is published but stale — one command for both.
            ->expectsOutputToContain('php artisan lang:add de nl')
            ->doesntExpectOutputToContain('composer require --dev laravel-lang/common')
            ->assertExitCode(0);
    }

    /**
     * A published locale the template ships no strings of its own for cannot be
     * made stale — there is no stub to copy over it — so once it is published there
     * is nothing left to say. Saying something anyway would train the reader to
     * ignore the message on the run where it matters.
     */
    public function test_a_published_locale_without_a_template_stub_says_nothing(): void
    {
        // Swedish: the template ships de/es/fr/it/nl/pl/pt, so there is no sv stub.
        $this->markAsPublished('sv');
        $this->installLaravelLang();

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'sv',
        ])
            ->doesntExpectOutputToContain('laravel-lang')
            ->doesntExpectOutputToContain('lang:add')
            ->assertExitCode(0);
    }

    /**
     * The other half of the same rule: --fresh copies the template's stub back over
     * lang/<code>.json, so a locale the template does ship strings for is stale
     * again after every re-run, however complete it was before. That is what the
     * repair path exists for.
     */
    public function test_a_rerun_makes_a_shipped_locale_stale_again(): void
    {
        $this->markAsPublished('nl');
        $this->installLaravelLang();

        @mkdir($this->temp.'/lang', 0777, true);
        file_put_contents(
            $this->temp.'/lang/nl.json',
            json_encode(['Search' => 'Zoeken', 'validation.required' => 'Verplicht'], JSON_PRETTY_PRINT),
        );

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])
            ->expectsOutputToContain('Merge Laravel\'s own translations back with: php artisan lang:add nl')
            ->assertExitCode(0);
    }

    /**
     * English is what Laravel's strings already are, so it never joins either list
     * even when its json matches the stub.
     */
    public function test_english_is_never_repaired(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'en,nl',
        ])
            ->expectsOutputToContain('lang:add nl')
            ->doesntExpectOutputToContain('lang:add en')
            ->assertExitCode(0);
    }
}
