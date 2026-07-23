<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use App\Models\Page;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Commands\UserCommand;
use NickDeKruijk\Leap\ServiceProvider;
use NickDeKruijk\LeapTemplate\Tests\Concerns\BuildsTempApp;
use NickDeKruijk\LeapTemplate\Tests\Fixtures\User;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

class TemplateInstallTest extends TestCase
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
     * Content types are named in English whatever the site speaks, because the name is code
     * — a class, a table, a registry key — and never a URL. URLs come from the slug of the
     * page that lists the type, per locale, so a Dutch site is /berichten with a Story
     * model. That makes Str::plural right by construction, and the question it used to ask
     * ("Plural of Story?") a question with one correct answer.
     */
    public function test_the_plural_is_derived_without_asking(): void
    {
        require_once dirname(__DIR__).'/Fixtures/app-models-page.php';

        // No expectsQuestion for a plural: one reaching the console fails this test.
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--no-tags' => true,
            '--models' => 'Story', '--locales' => 'nl',
        ])->assertExitCode(0);

        $this->assertStringContainsString(
            "protected \$table = 'stories';",
            file_get_contents($this->temp.'/app/Models/Story.php'),
            'Str::plural handles an English name, which is the whole reason not to ask.',
        );
    }

    /**
     * The installer patches routes/web.php, DatabaseSeeder and config/leap.php for you, so
     * leaving four known lines on the User model as homework was out of step — and without
     * them /admin has no roles, no 2FA and no passkeys.
     */
    public function test_fresh_patches_the_user_model(): void
    {
        file_put_contents($this->temp.'/app/Models/User.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
}

PHP);

        // No prompt: --fresh answers it, like every other question.
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])->assertExitCode(0);

        $user = file_get_contents($this->temp.'/app/Models/User.php');
        $this->assertStringContainsString('implements PasskeyUser', $user);
        $this->assertStringContainsString('use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;', $user);
        $this->assertStringContainsString('use NickDeKruijk\Leap\Traits\HasRoles;', $user);
    }

    /**
     * Registering a seeder that was never copied is worse than not registering one:
     * DatabaseSeeder would call a class that does not exist, so db:seed fatals on the whole
     * project rather than just skipping the sample pages. The prompt defaulted to yes.
     */
    /**
     * Leap's role_user migration seeds the superuser role and attaches the first existing
     * user — but a fresh install has no user yet, and the installer only seeds PageSeeder.
     * The account that db:seed leaves behind therefore has no role, which RequireRole
     * answers with a 403. The last step of the install closes that gap.
     */
    public function test_fresh_leaves_behind_a_user_that_can_open_the_panel(): void
    {
        $this->prepareLeapDatabase();

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])->assertExitCode(0);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user, 'The install must end with a user to log in as.');

        $role = $user->roles()->wherePivot('accepted', true)->first();
        $this->assertNotNull($role, 'A user without an accepted role is 403ed by RequireRole.');
        $this->assertSame([['_name' => 'all_modules', 'all_permissions' => true]], $role->permissions);
    }

    public function test_declining_the_admin_user_leaves_the_hint_in_the_summary(): void
    {
        $this->prepareLeapDatabase();

        $this->artisan('leap:template', ['--models' => '', '--locales' => 'nl', '--no-install' => true])
            ->expectsConfirmation('Copy the page tree?', 'no')
            ->expectsConfirmation('Copy PageSeeder?', 'no')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'no')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'no')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'no')
            ->expectsConfirmation('Copy the starter tests?', 'no')
            ->expectsConfirmation('Add sitemap.xml route?', 'no')
            ->expectsConfirmation('Serve / from the page tree?', 'no')
            ->expectsConfirmation('Copy Nederlands translations?', 'no')
            ->expectsConfirmation('Add the Leap traits to your User model?', 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->expectsConfirmation('Create an admin user now?', 'no')
            ->expectsOutputToContain('php artisan leap:user you@example.com --role')
            ->assertExitCode(0);

        $this->assertSame(0, User::count());
    }

    /**
     * The tables the admin-user step needs: a users table and leap's roles/role_user pair,
     * whose migration seeds the superuser role. leap:user is registered by hand because
     * these tests deliberately don't boot leap's service provider.
     */
    private function prepareLeapDatabase(): void
    {
        config([
            'leap.table_prefix' => 'leap_',
            'leap.credentials' => ['email', 'password'],
            'auth.providers.users.model' => User::class,
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $migrations = dirname((new \ReflectionClass(ServiceProvider::class))->getFileName(), 2).'/migrations';
        foreach (['2023_00_00_000001_create_leap_roles_table.php', '2023_00_00_000002_create_leap_role_user_table.php'] as $migration) {
            (require $migrations.'/'.$migration)->up();
        }

        $this->app[Kernel::class]->registerCommand(new UserCommand);
    }

    public function test_the_seeder_is_not_registered_when_it_was_not_copied(): void
    {
        $this->artisan('leap:template', ['--models' => '', '--locales' => 'nl', '--no-install' => true])
            ->expectsConfirmation('Copy the page tree?', 'no')
            ->expectsConfirmation('Copy PageSeeder?', 'no')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'no')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'no')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'no')
            ->expectsConfirmation('Copy the starter tests?', 'no')
            ->expectsConfirmation('Add sitemap.xml route?', 'no')
            ->expectsConfirmation('Serve / from the page tree?', 'no')
            ->expectsConfirmation('Copy Nederlands translations?', 'no')
            ->expectsConfirmation('Add the Leap traits to your User model?', 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->assertExitCode(0);

        $this->assertStringNotContainsString(
            'PageSeeder',
            file_get_contents($this->temp.'/database/seeders/DatabaseSeeder.php'),
            'DatabaseSeeder must not call a seeder that is not there.',
        );
    }

    /**
     * A fresh Laravel app ships DatabaseSeeder with WithoutModelEvents enabled, and a leap
     * model generates its slug on the saving event — so seeding under it produced items with
     * a null slug: unreachable detail pages, links rendered as "/news/", and no sitemap entry.
     * Pages escaped it (PageSeeder writes their slugs literally), which is what made it quiet.
     */
    public function test_without_model_events_is_removed_from_the_database_seeder(): void
    {
        // Verbatim from a fresh Laravel app.
        file_put_contents($this->temp.'/database/seeders/DatabaseSeeder.php', <<<'PHP'
            <?php

            namespace Database\Seeders;

            use App\Models\User;
            use Illuminate\Database\Console\Seeds\WithoutModelEvents;
            use Illuminate\Database\Seeder;

            class DatabaseSeeder extends Seeder
            {
                use WithoutModelEvents;

                /**
                 * Seed the application's database.
                 */
                public function run(): void
                {
                    User::factory()->create([
                        'name' => 'Test User',
                    ]);
                }
            }

            PHP);

        $this->artisan('leap:template', ['--models' => '', '--locales' => 'nl', '--no-install' => true])
            ->expectsConfirmation('Copy the page tree?', 'no')
            ->expectsConfirmation('Copy PageSeeder?', 'yes')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'no')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'no')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'no')
            ->expectsConfirmation('Copy the starter tests?', 'no')
            ->expectsConfirmation('Add sitemap.xml route?', 'no')
            ->expectsConfirmation('Serve / from the page tree?', 'no')
            ->expectsConfirmation('Register PageSeeder in DatabaseSeeder?', 'yes')
            ->expectsConfirmation('Remove WithoutModelEvents from DatabaseSeeder?', 'yes')
            ->expectsConfirmation('Copy Nederlands translations?', 'no')
            ->expectsConfirmation('Add the Leap traits to your User model?', 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->assertExitCode(0);

        $seeder = file_get_contents($this->temp.'/database/seeders/DatabaseSeeder.php');

        $this->assertStringNotContainsString('WithoutModelEvents', $seeder);
        // The rest of the class has to survive: the import went, not the file.
        $this->assertStringContainsString('use App\Models\User;', $seeder);
        $this->assertStringContainsString('PageSeeder::class', $seeder);
        $this->assertStringContainsString("'name' => 'Test User',", $seeder);
        $this->assertValidPhp($seeder);
    }

    /**
     * A DatabaseSeeder that never muted events must not be touched, nor asked about.
     */
    public function test_the_database_seeder_is_left_alone_when_events_are_not_muted(): void
    {
        $this->artisan('leap:template', ['--models' => '', '--locales' => 'nl', '--no-install' => true])
            ->expectsConfirmation('Copy the page tree?', 'no')
            ->expectsConfirmation('Copy PageSeeder?', 'yes')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'no')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'no')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'no')
            ->expectsConfirmation('Copy the starter tests?', 'no')
            ->expectsConfirmation('Add sitemap.xml route?', 'no')
            ->expectsConfirmation('Serve / from the page tree?', 'no')
            ->expectsConfirmation('Register PageSeeder in DatabaseSeeder?', 'yes')
            ->expectsConfirmation('Copy Nederlands translations?', 'no')
            ->expectsConfirmation('Add the Leap traits to your User model?', 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->assertExitCode(0);

        $this->assertStringContainsString(
            'PageSeeder::class',
            file_get_contents($this->temp.'/database/seeders/DatabaseSeeder.php'),
        );
    }

    private function assertValidPhp(string $code): void
    {
        $file = tempnam(sys_get_temp_dir(), 'seeder').'.php';
        file_put_contents($file, $code);
        exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);
        unlink($file);

        $this->assertSame(0, $status, 'DatabaseSeeder is not valid PHP: '.implode("\n", $output));
    }

    /**
     * The tag filter is one decision. HasTags used to be asked in the main run, before the
     * question that decides whether App\Models\Tag — the class it points at — is ever
     * created, so --no-tags left a trait referring to a model that does not exist.
     */
    public function test_no_tags_leaves_no_tag_files_behind(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--no-tags' => true,
            // Tags live on content types, so there has to be one to leave them off.
            '--models' => 'News', '--locales' => 'nl',
        ])->assertExitCode(0);

        foreach ([
            'app/Traits/HasTags.php',
            'app/Models/Tag.php',
            'app/Leap/Tag.php',
            'database/factories/TagFactory.php',
        ] as $file) {
            $this->assertFileDoesNotExist($this->temp.'/'.$file, "--no-tags must not install {$file}.");
        }
    }

    public function test_tags_installs_the_whole_filter_without_asking_five_more_times(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--tags' => true,
            '--models' => 'News', '--locales' => 'nl',
        ])->assertExitCode(0);

        foreach ([
            'app/Traits/HasTags.php',
            'app/Models/Tag.php',
            'app/Leap/Tag.php',
            'database/factories/TagFactory.php',
            'database/migrations/2025_01_03_094210_create_tags_table.php',
            'database/migrations/2025_01_03_094211_create_taggables_table.php',
        ] as $file) {
            $this->assertFileExists($this->temp.'/'.$file, "Choosing tags must install {$file}.");
        }
    }

    /**
     * The route and the view are one thing. They used to be two prompts with opposite
     * defaults — the view yes, the route no — so taking both left
     * Route::get('/', fn () => view('welcome')) pointing at a view that was gone, and /
     * threw "View [welcome] not found" on a site the installer had just built.
     */
    public function test_the_welcome_route_and_view_go_together(): void
    {
        mkdir($this->temp.'/resources/views', 0777, true);
        file_put_contents($this->temp.'/resources/views/welcome.blade.php', 'welcome');

        $this->artisan('leap:template', ['--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl'])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($this->temp.'/resources/views/welcome.blade.php');
        $this->assertStringNotContainsString(
            "return view('welcome');",
            file_get_contents($this->temp.'/routes/web.php'),
            'The view is gone, so the route pointing at it must be too.',
        );
    }

    /**
     * The welcome route was matched byte-for-byte against the default multi-line block, so
     * a reformatted route (Pint, an arrow fn, a one-liner) slipped past: the catch-all got
     * added while the welcome route stayed, and / kept the welcome view. The match is now
     * tolerant of formatting.
     */
    public function test_a_reformatted_welcome_route_is_still_removed(): void
    {
        mkdir($this->temp.'/resources/views', 0777, true);
        file_put_contents($this->temp.'/resources/views/welcome.blade.php', 'welcome');
        // A one-line arrow-fn variant of the same route.
        file_put_contents($this->temp.'/routes/web.php', "<?php\n\nRoute::get('/', fn () => view('welcome'));\n");

        $this->artisan('leap:template', ['--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl'])
            ->assertExitCode(0);

        $routes = file_get_contents($this->temp.'/routes/web.php');
        $this->assertStringNotContainsString("view('welcome')", $routes, 'The reformatted welcome route must be removed too.');
        $this->assertStringContainsString("PageController::class, 'route'", $routes, 'The catch-all should replace it.');
    }

    public function test_keeping_the_welcome_page_keeps_both_halves(): void
    {
        mkdir($this->temp.'/resources/views', 0777, true);
        file_put_contents($this->temp.'/resources/views/welcome.blade.php', 'welcome');

        $this->artisan('leap:template', ['--models' => '', '--locales' => 'nl'])
            ->expectsConfirmation('Copy the page tree?', 'no')
            ->expectsConfirmation('Copy PageSeeder?', 'no')
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'no')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'no')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'no')
            ->expectsConfirmation('Copy the starter tests?', 'no')
            ->expectsConfirmation('Add sitemap.xml route?', 'no')
            ->expectsConfirmation('Serve / from the page tree?', 'no')
            ->expectsConfirmation('Copy Nederlands translations?', 'no')
            ->expectsConfirmation('Add the Leap traits to your User model?', 'no')
            ->expectsConfirmation('Run "composer require" for the missing packages now?', 'no')
            ->expectsConfirmation("Install Laravel's own translations (validation, auth) for nl?", 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
            ->assertExitCode(0);

        $this->assertFileExists($this->temp.'/resources/views/welcome.blade.php');
        $this->assertStringContainsString("return view('welcome');", file_get_contents($this->temp.'/routes/web.php'));
    }

    /**
     * --fresh is the unattended path: every prompt answered yes, nothing asked. It had no
     * test at all, so each change to the interactive flow was a guess about this one.
     *
     * --no-install keeps it off Packagist; without it a --fresh run really does composer
     * require, which is right for an install and wrong for a test suite.
     */
    public function test_fresh_installs_everything_without_asking(): void
    {
        // No expectsConfirmation: a single prompt reaching the console fails this test,
        // which is the point of --fresh.
        $this->artisan('leap:template', [
            '--fresh' => true,
            '--no-install' => true,
            '--models' => '',
            '--locales' => 'nl',
        ])->assertExitCode(0);

        foreach ([
            'app/Http/Controllers/PageController.php',
            'app/Models/Page.php',
            'app/Leap/Page.php',
            'app/Leap/Concerns/ContentSections.php',
            'app/Livewire/Search.php',
            'database/migrations/2025_01_03_094203_create_pages_table.php',
            'database/seeders/PageSeeder.php',
            'lang/nl.json',
            'public/css/tinymce.css',
            'tests/Feature/SearchTest.php',
            'resources/views/layouts/app.blade.php',
        ] as $file) {
            $this->assertFileExists($this->temp.'/'.$file, "Expected --fresh to install {$file}.");
        }

        // The language was applied rather than merely asked about.
        $this->assertStringContainsString('APP_LOCALE=nl', file_get_contents($this->temp.'/.env'));
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
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'no')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'no')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'no')
            ->expectsConfirmation('Copy the starter tests?', 'no')
            ->expectsConfirmation('Add sitemap.xml route?', 'no')
            ->expectsConfirmation('Serve / from the page tree?', 'no')
            ->expectsConfirmation('Copy Nederlands translations?', 'no')
            ->expectsConfirmation('Add the Leap traits to your User model?', 'no')
            ->expectsConfirmation('Run "composer require" for the missing packages now?', 'no')
            ->expectsConfirmation("Install Laravel's own translations (validation, auth) for nl?", 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
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
            ->expectsConfirmation('Copy TinyMCE editor stylesheet?', 'yes')
            ->expectsConfirmation('Link public/storage to storage/app/public?', 'yes')
            ->expectsConfirmation('Copy ImageResize config (frontend resize templates)?', 'yes')
            ->expectsConfirmation('Copy the starter tests?', 'yes')
            ->expectsConfirmation('Add sitemap.xml route?', 'yes')
            ->expectsConfirmation('Serve / from the page tree?', 'yes')
            ->expectsConfirmation('Register PageSeeder in DatabaseSeeder?', 'yes')
            // configureLocales() runs here, so this is asked only once the language is
            // known, and about the language actually chosen -- nl. English gets no file:
            // the views are written in English and fall back to the key.
            ->expectsConfirmation('Copy Nederlands translations?', 'yes')
            ->expectsConfirmation('Add the Leap traits to your User model?', 'no')
            ->expectsConfirmation('Run "composer require" for the missing packages now?', 'no')
            ->expectsConfirmation("Install Laravel's own translations (validation, auth) for nl?", 'no')
            ->expectsConfirmation('Run database migrations now?', 'no')
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

    /**
     * lang/nl.json holds the template's own strings; Laravel's — the validation errors, the
     * password mails — come from laravel-lang, and a Dutch install used to leave them English
     * with nothing said about it.
     *
     * --no-install is the offline half of the step, so this asserts the instructions rather
     * than a directory: the files themselves need Packagist.
     */
    public function test_the_install_says_how_to_get_laravels_own_translations(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])
            // One assertion, because both halves are printed on one line: two substrings of
            // the same write only ever match the first expectation.
            ->expectsOutputToContain('composer require --dev laravel-lang/common && php artisan lang:add nl')
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($this->temp.'/lang/nl', '--no-install must stay off the network.');
    }

    /**
     * Every chosen language in one command — laravel-lang takes them as a list, and asking
     * per language would be the same question twice.
     */
    public function test_every_chosen_language_is_named_at_once(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl,de',
        ])
            ->expectsOutputToContain('php artisan lang:add nl de')
            ->assertExitCode(0);
    }

    /**
     * English is what Laravel's own strings already are, so an English-only site has nothing
     * to install and must not be asked about it.
     */
    public function test_an_english_site_is_not_asked_about_translations(): void
    {
        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'en',
        ])
            ->doesntExpectOutputToContain('laravel-lang')
            ->assertExitCode(0);
    }

    /**
     * A second run over an installed project does not ask again — lang/nl is there and the
     * package with it. What it does have to do is merge laravel-lang's keys back into
     * lang/nl.json, because the run just copied the template's stub over them. That is not a
     * question: saying yes to the overwrite already answered it.
     */
    public function test_a_rerun_merges_the_framework_strings_back_into_the_json(): void
    {
        mkdir($this->temp.'/lang/nl', 0777, true);
        file_put_contents($this->temp.'/lang/nl/validation.php', "<?php\n\nreturn [];\n");
        mkdir($this->temp.'/vendor/laravel-lang/common', 0777, true);

        $this->artisan('leap:template', [
            '--fresh' => true, '--no-install' => true, '--models' => '', '--locales' => 'nl',
        ])
            // No composer require: the package is already a dev dependency.
            ->doesntExpectOutputToContain('composer require --dev laravel-lang/common')
            ->expectsOutputToContain('Merge Laravel\'s own translations back with: php artisan lang:add nl')
            ->assertExitCode(0);

        $this->assertSame("<?php\n\nreturn [];\n", file_get_contents($this->temp.'/lang/nl/validation.php'));
    }
}
