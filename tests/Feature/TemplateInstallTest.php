<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\Leap\ServiceProvider;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

class TemplateInstallTest extends TestCase
{
    private string $temp;

    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd();
        $this->temp = sys_get_temp_dir().'/leap-template-'.uniqid();

        // Minimal Laravel-app skeleton: the directories a bare app already ships with,
        // plus the files the patch steps expect to edit. Deliberately not app/Leap,
        // app/Livewire, app/Support, lang, public/css or tests/Feature — those are the
        // ones copyOrReplace has to create on its own.
        foreach ([
            'app/Http/Controllers', 'app/Models', 'database/migrations',
            'database/seeders', 'config', 'public', 'tests', 'routes',
        ] as $dir) {
            mkdir($this->temp.'/'.$dir, 0777, true);
        }

        // leap's shipped config lives in the leap package, not this one — locate it by
        // reflecting on its ServiceProvider (works via vendor or the local path repo).
        $leapConfig = dirname((new \ReflectionClass(ServiceProvider::class))->getFileName(), 2).'/config/leap.php';
        copy($leapConfig, $this->temp.'/config/leap.php');
        file_put_contents($this->temp.'/routes/web.php', "<?php\n\nRoute::get('/', function () {\n    return view('welcome');\n});\n");
        file_put_contents($this->temp.'/database/seeders/DatabaseSeeder.php', "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\n\nclass DatabaseSeeder extends Seeder\n{\n    public function run(): void\n    {\n    }\n}\n");
        file_put_contents($this->temp.'/app/Models/User.php', "<?php\n\nnamespace App\\Models;\n\nclass User {}\n");
        file_put_contents($this->temp.'/.env', "APP_LOCALE=en\nAPP_FALLBACK_LOCALE=en\n");

        // One of the two compiled-asset rules is already here, so a re-run has to
        // add the missing one without duplicating the other
        file_put_contents($this->temp.'/.gitignore', "/vendor\n/public/css/builds\n");

        $this->app->setBasePath($this->temp);
        chdir($this->temp);

        // storage:link reads filesystems.links, which was resolved against the real
        // application path before the base path moved here
        mkdir($this->temp.'/storage/app/public', 0777, true);
        config(['filesystems.links' => [
            $this->temp.'/public/storage' => $this->temp.'/storage/app/public',
        ]]);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->deleteDir($this->temp);

        parent::tearDown();
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * The set is one question only while none of it is there. A file you already have is
     * your copy, and you may well have edited one and not the others — so a re-run asks
     * about those one by one, and taking the missing pieces must not sweep your edits
     * along with them.
     */
    public function test_a_rerun_installs_what_is_missing_without_touching_an_edited_file(): void
    {
        // app/Models is already part of the skeleton a bare Laravel app ships with.
        file_put_contents($this->temp.'/app/Models/Page.php', '<?php // my own Page model');

        $this->artisan('leap:template', ['--models' => '', '--locales' => 'nl'])
            // Asked for the rest of the set, which is still missing.
            ->expectsConfirmation('Copy the page tree?', 'yes')
            // And separately about the one that is already here.
            ->expectsConfirmation('Page model already exists, do you want to overwrite it?', 'no')
            ->expectsConfirmation('Copy PageSeeder?', 'no')
            ->expectsConfirmation('Copy HasTags trait?', 'no')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'no')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'no')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'no')
            ->expectsConfirmation('Copy the starter tests?', 'no')
            ->expectsConfirmation('Run "composer require" for the missing packages now?', 'no')
            ->expectsConfirmation('Delete default Laravel welcome route?', 'no')
            ->expectsConfirmation('Add sitemap.xml route?', 'no')
            ->expectsConfirmation('Add PageController route?', 'no')
            ->expectsConfirmation('Register PageSeeder in DatabaseSeeder?', 'no')
            ->expectsConfirmation('Copy Nederlands translations?', 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->expectsConfirmation('Seed the sample pages now?', 'no')
            ->assertExitCode(0);

        $this->assertSame(
            '<?php // my own Page model',
            file_get_contents($this->temp.'/app/Models/Page.php'),
            'An edited file must not be swept along with the set.',
        );
        $this->assertFileExists($this->temp.'/app/Http/Controllers/PageController.php', 'The missing files are still installed.');
        $this->assertFileExists($this->temp.'/app/Livewire/Search.php');
    }

    public function test_leap_template_installs_into_a_bare_app(): void
    {
        // --models='' skips content generation (no leap:content), --locales sets the
        // languages without the multiselect. The rest is driven interactively; the
        // per-file resources copy (copyDir) writes new files silently.
        $this->artisan('leap:template', ['--models' => '', '--locales' => 'nl,en'])
            // One question for the six files that only work together, not six.
            ->expectsConfirmation('Copy the page tree?', 'yes')
            ->expectsConfirmation('Copy PageSeeder?', 'yes')
            ->expectsConfirmation('Copy HasTags trait?', 'yes')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'yes')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'yes')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'yes')
            ->expectsConfirmation('Copy the starter tests?', 'yes')
            ->expectsConfirmation('Run "composer require" for the missing packages now?', 'no')
            ->expectsConfirmation('Delete default Laravel welcome route?', 'yes')
            ->expectsConfirmation('Add sitemap.xml route?', 'yes')
            ->expectsConfirmation('Add PageController route?', 'yes')
            ->expectsConfirmation('Register PageSeeder in DatabaseSeeder?', 'yes')
            // configureLocales() runs here, so this is asked only once the language is
            // known, and about the language actually chosen -- nl. English gets no file:
            // the views are written in English and fall back to the key.
            ->expectsConfirmation('Copy Nederlands translations?', 'yes')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->expectsConfirmation('Seed the sample pages now?', 'no')
            ->assertExitCode(0);

        // Representative copies landed
        foreach ([
            'app/Http/Controllers/PageController.php',
            'app/Models/Page.php',
            'app/Leap/Page.php',
            'app/Livewire/Search.php',
            'app/Leap/Concerns/ContentSections.php',
            'database/migrations/2025_01_03_094203_create_pages_table.php',
            // Shipped as stubs but never copied until 0.10.9, while testing features the
            // template does ship: the live search and the SEO tags.
            'tests/Feature/SearchTest.php',
            'tests/Feature/SeoTest.php',
            'config/imageresize.php',
            'public/css/tinymce.css',
            'resources/views/sections/default.blade.php',
            'resources/views/sections/video.blade.php',
            'resources/views/sections/cookies.blade.php',
            'resources/css/template.scss',
        ] as $file) {
            $this->assertFileExists($this->temp.'/'.$file, "Expected {$file} to be copied.");
        }

        // The chosen language gets its translations; English never does, because the views
        // are written in English and Laravel falls back to the key.
        $this->assertFileExists($this->temp.'/lang/nl.json');
        $this->assertFileDoesNotExist($this->temp.'/lang/en.json');
        // Nor do languages nobody picked.
        $this->assertFileDoesNotExist($this->temp.'/lang/de.json');

        // Media lives on the public disk and is served from /storage. Without the
        // link nothing an editor uploads renders, and the failure is opaque: the
        // file is plainly on disk, but asset_resized() calls the original missing.
        $this->assertTrue(
            is_link($this->temp.'/public/storage'),
            'leap:template must link public/storage, or no uploaded image resolves.',
        );

        // The compiled CSS/JS is build output, written on request by minify, so it
        // is kept out of version control rather than committed as a stale artifact
        $gitignore = file_get_contents($this->temp.'/.gitignore');
        $this->assertStringContainsString('/public/js/builds', $gitignore);
        // The ignored directory has to be the one the config it just installed writes
        // to — not the package default that was loaded at boot. Get that wrong and the
        // resize cache quietly lands in git.
        $route = (include $this->temp.'/config/imageresize.php')['route'];
        $this->assertSame('resized', $route);
        $this->assertStringContainsString('/public/'.$route, $gitignore);
        $this->assertStringNotContainsString('/public/media', $gitignore);
        $this->assertSame(1, substr_count($gitignore, '/public/css/builds'), 'The rule was already there and must not be added twice.');

        // Route + config patches applied
        $routes = file_get_contents($this->temp.'/routes/web.php');
        $this->assertStringContainsString('PageController::class', $routes);

        // Imported, not written out fully qualified inline: Pint rejects the latter, which
        // left every scaffolded project failing its own style check on a file it never
        // wrote itself.
        $this->assertStringContainsString('use App\Http\Controllers\PageController;', $routes);
        $this->assertStringNotContainsString('[App\Http\Controllers\PageController::class', $routes);
        $this->assertSame(1, substr_count($routes, 'use App\Http\Controllers\PageController;'));
        $this->assertStringNotContainsString("return view('welcome');", $routes);

        $leapConfig = file_get_contents($this->temp.'/config/leap.php');
        $this->assertStringContainsString("'content_css' => '/css/tinymce.css'", $leapConfig);
        $this->assertStringContainsString("'nl' => 'Nederlands'", $leapConfig);

        $seeder = file_get_contents($this->temp.'/database/seeders/DatabaseSeeder.php');
        $this->assertStringContainsString('PageSeeder::class', $seeder);
    }
}
