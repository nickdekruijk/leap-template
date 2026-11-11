<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use NickDeKruijk\LeapTemplate\Tests\Concerns\BuildsTempApp;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * leap:template sets a project up for Larastan: phpstan.neon at level 5, the
 * `composer types:check` script and larastan/larastan as a dev dependency. The template
 * passes it as installed (the CI scaffold job runs it), so these tests are about the three
 * pieces landing, and about what an existing project keeps.
 */
class StaticAnalysisTest extends TestCase
{
    use BuildsTempApp;

    /**
     * The scripts block of Laravel's skeleton, shortened, with the inline arrays and the
     * empty object a decode and encode would reformat.
     */
    private const COMPOSER_JSON = <<<'JSON'
        {
            "name": "laravel/laravel",
            "keywords": ["laravel", "framework"],
            "require": {
                "php": "^8.3"
            },
            "scripts": {
                "post-autoload-dump": [
                    "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
                    "@php artisan package:discover --ansi"
                ],
                "test": [
                    "@php artisan config:clear --ansi",
                    "@php artisan test"
                ]
            },
            "extra": {
                "laravel": {
                    "dont-discover": []
                }
            },
            "config": {}
        }

        JSON;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTempApp();
        file_put_contents($this->temp.'/composer.json', self::COMPOSER_JSON);
    }

    protected function tearDown(): void
    {
        $this->removeTempApp();

        parent::tearDown();
    }

    private function fresh(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])->assertExitCode(0);
    }

    public function test_a_fresh_install_gets_the_config_the_script_and_the_package_line(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])
            ->expectsOutputToContain('Install Larastan with: composer require --dev larastan/larastan')
            ->assertExitCode(0);

        $this->assertFileEquals(dirname(__DIR__, 2).'/stubs/template/phpstan.neon', $this->temp.'/phpstan.neon');

        $json = json_decode(file_get_contents($this->temp.'/composer.json'), true);
        $this->assertSame('phpstan analyse --memory-limit=2G', $json['scripts']['types:check']);

        $this->assertDirectoryDoesNotExist($this->temp.'/vendor/larastan', '--no-install must stay off the network.');
    }

    /**
     * One line more in composer.json, and nothing else touched: json_encode() would spread
     * "keywords" over four lines and turn the empty "config" object into an array.
     */
    public function test_the_script_is_written_into_composer_json_without_reformatting_it(): void
    {
        $this->fresh();

        $expected = str_replace(
            "            \"@php artisan test\"\n        ]\n    },",
            "            \"@php artisan test\"\n        ],\n        \"types:check\": \"phpstan analyse --memory-limit=2G\"\n    },",
            self::COMPOSER_JSON,
        );

        $this->assertSame($expected, file_get_contents($this->temp.'/composer.json'));
    }

    public function test_an_empty_scripts_object_gets_the_script_without_a_stray_comma(): void
    {
        file_put_contents($this->temp.'/composer.json', "{\n    \"scripts\": {}\n}\n");

        $this->fresh();

        $this->assertSame(
            "{\n    \"scripts\": {\n        \"types:check\": \"phpstan analyse --memory-limit=2G\"\n    }\n}\n",
            file_get_contents($this->temp.'/composer.json'),
        );
    }

    public function test_a_composer_json_without_scripts_is_left_alone_and_says_what_to_add(): void
    {
        file_put_contents($this->temp.'/composer.json', "{\n    \"name\": \"x/y\"\n}\n");

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])
            ->expectsOutputToContain('"types:check": "phpstan analyse --memory-limit=2G"')
            ->assertExitCode(0);

        $this->assertSame("{\n    \"name\": \"x/y\"\n}\n", file_get_contents($this->temp.'/composer.json'));
    }

    /**
     * A project's phpstan.neon is where its baseline is included. Overwriting it with the
     * stub, as --fresh does with every other changed file, would turn its check red.
     */
    public function test_an_existing_phpstan_neon_is_never_replaced(): void
    {
        $own = "includes:\n    - vendor/larastan/larastan/extension.neon\n    - phpstan-baseline.neon\n";
        file_put_contents($this->temp.'/phpstan.neon', $own);

        $this->fresh();

        $this->assertSame($own, file_get_contents($this->temp.'/phpstan.neon'));
        // The script is still added: it is the one piece the project did not have.
        $this->assertArrayHasKey('types:check', json_decode(file_get_contents($this->temp.'/composer.json'), true)['scripts']);
    }

    public function test_an_existing_script_is_kept(): void
    {
        file_put_contents($this->temp.'/composer.json', "{\n    \"scripts\": {\n        \"types:check\": \"phpstan analyse -c custom.neon\"\n    }\n}\n");

        $this->fresh();

        $this->assertStringContainsString('"types:check": "phpstan analyse -c custom.neon"', file_get_contents($this->temp.'/composer.json'));
        $this->assertSame(1, substr_count(file_get_contents($this->temp.'/composer.json'), 'types:check'));
    }

    public function test_diff_reports_what_a_project_from_before_2_6_is_missing(): void
    {
        $this->assertSame(0, Artisan::call('leap:template', ['--diff' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString('phpstan.neon (not in this project yet)', $output);
        $this->assertStringContainsString('✗ Larastan', $output);
        $this->assertStringContainsString('✗ composer types:check', $output);
    }

    /**
     * A site from before 2.6.0 has the contract once it re-ran leap:template, and its models
     * still without it: those are named, because each costs a report per property the page
     * engine reads.
     */
    public function test_diff_names_a_model_that_does_not_implement_the_content_contract(): void
    {
        require_once dirname(__DIR__).'/Fixtures/app-models-page.php';
        require_once dirname(__DIR__, 2).'/stubs/template/app/Contracts/Content.php';

        mkdir($this->temp.'/app/Contracts', 0777, true);
        copy(dirname(__DIR__, 2).'/stubs/template/app/Contracts/Content.php', $this->temp.'/app/Contracts/Content.php');

        $this->assertSame(0, Artisan::call('leap:template', ['--diff' => true]));

        $this->assertStringContainsString('✗ App\\Models\\Page implements Content', Artisan::output());
    }

    public function test_diff_is_satisfied_once_the_script_is_there(): void
    {
        $this->fresh();

        $this->assertSame(0, Artisan::call('leap:template', ['--diff' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString('✓ composer types:check', $output);
        $this->assertStringNotContainsString('phpstan.neon (not in this project yet)', $output);
    }
}
