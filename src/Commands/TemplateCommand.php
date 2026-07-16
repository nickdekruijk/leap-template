<?php

namespace NickDeKruijk\LeapTemplate\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class TemplateCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command. The negatable --tags and
     * --multilingual options are added in configure() (the signature parser has no
     * VALUE_NEGATABLE).
     *
     * @var string
     */
    protected $signature = 'leap:template
        {--diff : Show how this project\'s template files differ from the current stubs without changing anything}
        {--fresh : Complete install with no prompts (implies --force; content = --models or News,Event; language = --locales or nl only)}
        {--force : Skip the production confirmation}
        {--models= : Comma list of content types to generate, e.g. News,Event,Project or Bericht:news:berichten (default News,Event)}
        {--locales= : Comma list of locale codes, e.g. nl,en (default: nl only)}';

    /**
     * The locale codes offered in the language picker, code => display name.
     *
     * @var array<string, string>
     */
    protected array $localeNames = [
        'nl' => 'Nederlands',
        'en' => 'English',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'it' => 'Italiano',
        'pt' => 'Português',
        'pl' => 'Polski',
    ];

    protected function configure(): void
    {
        parent::configure();

        $this->getDefinition()->addOption(new InputOption('tags', null, InputOption::VALUE_NEGATABLE, 'Include the shared Tag filter on content types', null));
        $this->getDefinition()->addOption(new InputOption('multilingual', null, InputOption::VALUE_NEGATABLE, 'Enable multiple languages', null));
    }

    /**
     * Answer a prompt from the flags when it has a dedicated (negatable) option, else
     * yes under --fresh, else ask. Precedence: explicit --key/--no-key > --fresh >
     * interactive default.
     *
     * @param  string  $hint  One line under the question: what it is for, or what saying
     *                        no costs. Nobody installing this for the first time knows,
     *                        so it is required — a prompt without one is the bug.
     */
    protected function confirmStep(string $key, string $question, bool $default, string $hint): bool
    {
        if ($this->getDefinition()->hasOption($key) && ($opt = $this->option($key)) !== null) {
            return (bool) $opt;
        }
        if ($this->option('fresh')) {
            return true;
        }

        return confirm($question, $default, hint: $hint);
    }

    /**
     * A prompt with no dedicated flag: yes under --fresh, else ask.
     *
     * @param  string  $hint  See confirmStep().
     */
    protected function auto(string $question, bool $default, string $hint): bool
    {
        return $this->option('fresh') ? true : confirm($question, $default, hint: $hint);
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a basic template replacing the default Laravel welcome template';

    /**
     * Copy or replace a file from the stubs/template folder after confirmation, asks to overwrite if it exists and sha1 hashes differ
     *
     * The destination directory is created when it is missing, silently and only once
     * the copy is actually going ahead, so a directory never appears for a file that was
     * skipped. Asking separately would be two questions for one decision, and answering
     * no to the directory while saying yes to the file would leave copy() failing.
     *
     * @param  string  $file  The file including path relative to the stubs/template folder
     * @param  string  $description  The description of the file to show in confirmation
     * @param  string  $hint  One line on what the file is for, shown under the question
     * @return void
     */
    public function copyOrReplace(string $file, string $description, string $hint)
    {
        $exists = file_exists($file);

        // Skip if file exists and sha1 hashes match
        if ($exists && sha1_file(__DIR__.'/../../stubs/template/'.$file) == sha1_file($file)) {
            return;
        }

        // An existing file is a different question: it is your copy that is at stake, not
        // what the file does, so the hint says that instead.
        $overwriteHint = 'Your copy differs from the template. Overwriting loses any edits you made to it.';

        if ($this->auto(
            $exists ? ucfirst("$description already exists, do you want to overwrite it?") : "Copy $description?",
            ! $exists,
            $exists ? $overwriteHint : $hint,
        )) {
            if (! is_dir($directory = dirname($file)) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                $this->error('Could not create '.$directory.', skipping '.$file);

                return;
            }

            if (! copy(__DIR__.'/../../stubs/template/'.$file, $file)) {
                $this->error('Could not copy '.$file);

                return;
            }

            $this->info('Copied '.$file);
        } else {
            $this->info('Skipping '.$file);
        }
    }

    /**
     * Copy a directory file by file. A new file is copied silently (a fresh install
     * should not interrogate every view); an identical existing file is skipped; a
     * changed existing file asks before overwriting (default no, so a re-install keeps
     * your edits to css/views/js). Under --fresh every changed file is overwritten too.
     */
    public function copyDir(string $directory, string $description)
    {
        $stubBase = __DIR__.'/../../stubs/template/';
        $filesystem = new Filesystem;

        foreach ($filesystem->allFiles($stubBase.$directory) as $file) {
            $relative = $directory.'/'.$file->getRelativePathname();
            $stub = $stubBase.$relative;

            if (file_exists($relative)) {
                // Identical — nothing to do
                if (sha1_file($stub) === sha1_file($relative)) {
                    continue;
                }
                // Changed — do not clobber a local edit without asking
                if (! $this->auto(
                    "Overwrite changed $relative?",
                    false,
                    'Your copy differs from the template. Overwriting loses any edits you made to it.',
                )) {
                    continue;
                }
            }

            if (! is_dir($dir = dirname($relative))) {
                mkdir($dir, 0755, true);
            }
            copy($stub, $relative);
            $this->info('Copied '.$relative);
        }
    }

    /**
     * Offer to delete a leftover Laravel scaffolding file that the template
     * replaces. Always prompts (defaulting to Yes) rather than matching against
     * hardcoded sha1 hashes, so it keeps working across Laravel releases without
     * maintenance. The file is git-tracked in a fresh project, so an accidental
     * delete is recoverable.
     *
     * @param  string  $file  The file including path relative to base path
     * @return void
     */
    public function deleteFile(string $file)
    {
        if (file_exists($file) && $this->auto(
            "Delete $file? (Laravel default, replaced by the template)",
            true,
            'The template does not use it. It is in git in a fresh project, so you can get it back.',
        )) {
            unlink($file);
            $this->info('Deleted '.$file);
        }
    }

    /**
     * Update the contents of a file with the logic of a given callback
     *
     * @param  string  $file  The file to update
     * @param  callable  $callback  The callback function to run
     * @return void
     */
    public static function updateFile(string $file, callable $callback)
    {
        $originalFileContents = file_get_contents($file);
        $newFileContents = $callback($originalFileContents);
        file_put_contents($file, $newFileContents);
    }

    /**
     * Link public/storage, so uploaded media resolves.
     *
     * Leap stores media on the `public` disk (storage/app/public) and the template
     * serves it from /storage — both the originals and, through them, every resized
     * variant. Without the link nothing an editor uploads renders, and the failure
     * is opaque: the file is plainly there on disk, but asset_resized() reports the
     * original as missing. Mentioning it in the closing notes was not enough.
     */
    protected function linkStorage(): void
    {
        if (file_exists(public_path('storage'))) {
            return;
        }

        if ($this->auto(
            'Link public/storage to storage/app/public?',
            true,
            'Uploads are served from /storage. Without the link no image an editor uploads resolves.',
        )) {
            $this->call('storage:link');
        }
    }

    /**
     * Keep generated assets out of version control.
     *
     * minify writes public/css/builds and public/js/builds on request from the
     * sources under resources/; imageresize caches a resized copy of every image at
     * every width under the imageresize route. All of it is derived, all of it
     * regenerates on request, and committing it means every branch carries rebuilt
     * artifacts that conflict on merge — while a stale copy can mask a broken source.
     *
     * Only the resize cache is ignored, not the whole public/media directory: that
     * may well hold assets that are not generated.
     */
    protected function ignoreCompiledAssets(): void
    {
        $file = base_path('.gitignore');
        if (! file_exists($file)) {
            return;
        }

        // Read the route from the config file on disk, not from config(): this runs
        // after the template drops its own config/imageresize.php in, and the booted
        // config still holds whatever was loaded at startup — the package default on
        // a fresh install. Ignoring the wrong directory means the cache lands in git
        // and nobody notices.
        $route = 'media/resized';
        $config = base_path('config/imageresize.php');
        if (file_exists($config)) {
            $route = (include $config)['route'] ?? $route;
        }

        $cache = '/'.trim($route, '/');

        $contents = file_get_contents($file);
        $missing = array_values(array_filter(
            ['/public/css/builds', '/public/js/builds', '/public'.$cache],
            fn (string $rule): bool => ! preg_match('#^\s*'.preg_quote($rule, '#').'/?\s*$#m', $contents),
        ));

        if (! $missing) {
            return;
        }

        file_put_contents($file, rtrim($contents, "\n")."\n".implode("\n", $missing)."\n");
        $this->info('Added '.implode(' and ', $missing).' to .gitignore (compiled on request, not source)');
    }

    /**
     * Is this route already in routes/web.php?
     *
     * Matched on the controller reference rather than the whole line, so it also
     * recognises the fully qualified form this command used to write. Otherwise a
     * project that re-runs the installer would be told the route is missing and end up
     * with it twice.
     */
    protected function routeExists(string $needle): bool
    {
        return str_contains(file_get_contents(base_path('routes/web.php')), $needle);
    }

    /**
     * Import PageController in routes/web.php, so the routes below can name it plainly.
     *
     * Writing it fully qualified inline is what the command used to do, and Pint rejects
     * it (fully_qualified_strict_types) — which left every scaffolded project failing its
     * own style check on a file it never wrote.
     */
    protected function importPageController(): void
    {
        $import = "use App\Http\Controllers\PageController;\n";

        if ($this->routeExists($import)) {
            return;
        }

        self::updateFile(base_path('routes/web.php'), function (string $file) use ($import): string {
            // After the opening tag, ahead of any other import, and let Pint sort them
            return preg_replace('/^<\?php\s*\n/', "<?php\n\n".$import, $file, 1);
        });
    }

    /**
     * The template files copied by this command, as paths relative to the project
     * root (and to the stubs/template folder). Individual files plus everything in
     * the copied directories. Used by both the installer and --diff.
     *
     * @return array<int, string>
     */
    protected function templateFiles(): array
    {
        $files = [
            'app/Http/Controllers/PageController.php',
            'database/migrations/2025_01_03_094203_create_pages_table.php',
            'database/seeders/PageSeeder.php',
            'app/Models/Page.php',
            'app/Leap/Page.php',
            'app/Leap/Concerns/ContentSections.php',
            'app/Livewire/Search.php',
            'app/Traits/HasTags.php',
            'config/imageresize.php',
            'public/css/tinymce.css',
            'tests/Feature/PageRoutingTest.php',
            'tests/Feature/HasSlugTest.php',
            'tests/Feature/MultilingualTest.php',
        ];

        $stubBase = __DIR__.'/../../stubs/template';
        $filesystem = new Filesystem;
        foreach (['resources/css', 'resources/views', 'resources/js'] as $directory) {
            if (! is_dir($stubBase.'/'.$directory)) {
                continue;
            }
            foreach ($filesystem->allFiles($stubBase.'/'.$directory) as $file) {
                $files[] = $directory.'/'.$file->getRelativePathname();
            }
        }

        // Translations are per site: only the languages it chose were ever copied. A
        // lang file the project does not have is not drift — it was never meant to be
        // there — so listing all of them would report six phantom "new" files.
        foreach ($filesystem->glob($stubBase.'/lang/*.json') as $file) {
            if (file_exists(base_path($relative = 'lang/'.basename($file)))) {
                $files[] = $relative;
            }
        }

        return $files;
    }

    /**
     * Report how the project's template files differ from the current stubs,
     * without changing anything. Shows a unified diff per changed file when the
     * `diff` binary is available, and lists files that are new or unchanged.
     */
    public function showDiff(): int
    {
        $stubBase = realpath(__DIR__.'/../../stubs/template');
        $changed = $new = $unchanged = [];

        foreach ($this->templateFiles() as $relative) {
            $stub = $stubBase.'/'.$relative;
            $project = base_path($relative);

            if (! file_exists($project)) {
                $new[] = $relative;
            } elseif (sha1_file($stub) === sha1_file($project)) {
                $unchanged[] = $relative;
            } else {
                $changed[] = $relative;
            }
        }

        foreach ($changed as $relative) {
            $this->newLine();
            $this->line('<fg=yellow>changed:</> '.$relative);
            $output = [];
            exec('diff -u '.escapeshellarg(base_path($relative)).' '.escapeshellarg($stubBase.'/'.$relative).' 2>/dev/null', $output);
            foreach ($output as $line) {
                if (str_starts_with($line, '+')) {
                    $this->line('<fg=green>'.$line.'</>');
                } elseif (str_starts_with($line, '-')) {
                    $this->line('<fg=red>'.$line.'</>');
                } else {
                    $this->line($line);
                }
            }
        }

        foreach ($new as $relative) {
            $this->line('<fg=blue>new:</>     '.$relative.' (not in this project yet)');
        }

        $this->newLine();
        $this->info(count($changed).' changed, '.count($new).' new, '.count($unchanged).' unchanged.');

        return 0;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Report differences without touching anything
        if ($this->option('diff')) {
            return $this->showDiff();
        }

        // --fresh is a complete, unattended install: skip the production guard too.
        if ($this->option('fresh')) {
            $this->input->setOption('force', true);
        }

        // Don't run in production
        if (! $this->confirmToProceed()) {
            return 1;
        }

        // Publish the config (the content registry lives in config/leap.php)
        if (! file_exists('config/leap.php') && $this->auto(
            'Publish leap config file?',
            true,
            'config/leap.php: locales, the content-type registry and the admin settings. The template edits it.',
        )) {
            $this->call('vendor:publish', ['--provider' => 'NickDeKruijk\Leap\ServiceProvider', '--tag' => 'config']);
        }

        // Ask to copy or replace files (each creates its own directory as needed)
        $this->copyOrReplace('app/Http/Controllers/PageController.php', 'PageController', 'Serves every page and builds the menu. The frontend does not work without it.');
        $this->copyOrReplace('database/migrations/2025_01_03_094203_create_pages_table.php', 'pages table migration', 'Creates the pages table the Page model reads.');
        $this->copyOrReplace('database/seeders/PageSeeder.php', 'PageSeeder', 'Sample content: a home page and a few children, in every locale. Delete once the site has its own.');
        $this->copyOrReplace('app/Models/Page.php', 'Page model', 'The page itself: slug, sections, SEO fields and the page tree.');
        $this->copyOrReplace('app/Leap/Page.php', 'Page model Leap module', 'The screen in /admin where editors write pages.');
        // HasTags is the project's own: it hangs off App\Models\Tag, which is a stub and
        // optional (--no-tags). HasSections, HasSlug and the Video class are the package's,
        // and the models use them straight from there.
        $this->copyOrReplace('app/Traits/HasTags.php', 'HasTags trait', 'Lets content types carry shared tags, and their overviews filter on them.');

        // Shared content-section blocks (Page + every content type build from these)
        $this->copyOrReplace('app/Leap/Concerns/ContentSections.php', 'ContentSections concern', 'The section blocks the editor offers — text, image, video — shared by Page and every content type.');

        // Frontend English strings (used when a site is multilingual with English)

        // Live search (a plain Livewire class component so it works on Livewire 3 and 4)
        $this->copyOrReplace('app/Livewire/Search.php', 'Search Livewire component', 'Powers the live search box in the menu.');

        // TinyMCE editor content styles, so rich-text matches the frontend in the editor
        $this->copyOrReplace('public/css/tinymce.css', 'TinyMCE editor stylesheet', 'Makes rich text in the admin look like the frontend, so editors see what they get.');
        $this->enableTinymceContentCss();

        // Uploaded media lives on the public disk and is served from /storage
        $this->linkStorage();

        // ImageResize width presets used by the template's srcset/backgrounds
        // (overrides the vendor-published default, which lacks these templates)
        $this->copyOrReplace('config/imageresize.php', 'ImageResize config (frontend resize templates)', 'Width presets for responsive images. The package default lacks the ones the template asks for.');

        // Generated assets are not source. After the config above, because that is
        // what decides where the resize cache lands.
        $this->ignoreCompiledAssets();

        // Starter feature tests for the copied template code (run under the host's test suite)
        $this->copyOrReplace('tests/Feature/PageRoutingTest.php', 'PageRouting test', 'Starter test: every page resolves and renders. Runs in your own suite.');
        $this->copyOrReplace('tests/Feature/HasSlugTest.php', 'HasSlug test', 'Starter test: slugs stay unique per locale and per parent.');
        $this->copyOrReplace('tests/Feature/MultilingualTest.php', 'Multilingual test', 'Starter test: each locale routes and falls back correctly.');

        // Ask to delete default Laravel welcome view, js/app.js, app/bootstrap.js and css/app.css
        $this->deleteFile('resources/views/welcome.blade.php');
        $this->deleteFile('resources/js/app.js');
        $this->deleteFile('resources/js/bootstrap.js');
        $this->deleteFile('resources/css/app.css');
        // Laravel's stock ExampleTest asserts GET / returns 200 for the static welcome
        // page; the homepage is now DB-driven (PageController), so it would fail. The
        // template's PageRoutingTest covers routing properly instead.
        $this->deleteFile('tests/Feature/ExampleTest.php');

        // Ask to copy scss files, views and javascript
        $this->copyDir('resources/css', 'SCSS files');
        $this->copyDir('resources/views', 'template views');
        $this->copyDir('resources/js', 'JavaScript files');

        // Suggest installing the frontend packages the template relies on
        $this->suggestFrontendPackages();

        // Ask to delete Laravel default welcome route
        $route = "Route::get('/', function () {\n    return view('welcome');\n});\n";
        if (str_contains(file_get_contents('routes/web.php'), $route) && $this->auto(
            'Delete default Laravel welcome route?',
            false,
            'It would shadow the homepage: / is the page whose slug is /, not the welcome view.',
        )) {
            self::updateFile(base_path('routes/web.php'), function ($file) use ($route) {
                return str_replace($route, '', $file);
            });
        }

        // Ask to add the sitemap route (before the catch-all so it isn't swallowed)
        $sitemap = "Route::get('sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');\n";
        if (! $this->routeExists("PageController::class, 'sitemap'") && $this->auto(
            'Add sitemap.xml route?',
            true,
            'Publishes /sitemap.xml, built from the pages and content types. For search engines.',
        )) {
            $this->importPageController();
            self::updateFile(base_path('routes/web.php'), fn (string $file): string => $file.$sitemap);
        }

        // Ask to add PageController route
        $route = "Route::get('{any}', [PageController::class, 'route'])->where('any', '(.*)');\n";
        if (! $this->routeExists("PageController::class, 'route'") && $this->auto(
            'Add PageController route?',
            true,
            'The catch-all that serves every page. Without it the site has no pages at all.',
        )) {
            $this->importPageController();
            self::updateFile(base_path('routes/web.php'), fn (string $file): string => $file.$route);
        }

        // Generate the chosen content types (before migrate/seed, so their migrations run)
        $this->generateContentTypes();

        // Register the PageSeeder so `php artisan db:seed` seeds sample pages
        $this->registerPageSeeder();

        // Choose languages (must run before seeding — the seeders read leap.locales)
        $this->configureLocales();

        // Warn about the traits/contract the User model needs for Leap
        $this->checkUserModel();

        // Offer to run migrations and seed the sample pages
        $this->runMigrationsAndSeed();

        // Closing summary with the remaining manual steps
        $this->printNextSteps();
    }

    /**
     * Register the copied PageSeeder in DatabaseSeeder::run() so `php artisan
     * db:seed` (and `migrate --seed`) seed the sample pages. No-op if the call
     * is already present or DatabaseSeeder can't be located.
     */
    protected function registerPageSeeder(): void
    {
        $path = base_path('database/seeders/DatabaseSeeder.php');
        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if (str_contains($contents, 'PageSeeder')) {
            return;
        }

        // Insert the call as the first statement inside run() { ... }
        $patched = preg_replace(
            '/(function run\(\)(?:\s*:\s*void)?\s*\{)/',
            "$1\n        \$this->call(\\Database\\Seeders\\PageSeeder::class);",
            $contents,
            1
        );

        if ($patched && $patched !== $contents && $this->auto(
            'Register PageSeeder in DatabaseSeeder?',
            true,
            'So php artisan db:seed creates the sample pages too.',
        )) {
            file_put_contents($path, $patched);
            $this->info('Registered PageSeeder in DatabaseSeeder');
        }
    }

    /**
     * Point leap.tinymce.options.content_css at the copied /css/tinymce.css so the
     * rich-text editor loads the frontend button/prose styles. No-op when the leap
     * config isn't published, or the key is already customised.
     */
    protected function enableTinymceContentCss(): void
    {
        $config = base_path('config/leap.php');
        if (! file_exists($config)) {
            return;
        }

        $contents = file_get_contents($config);
        $commented = "// 'content_css' => '/css/tinymce.css',";
        if (str_contains($contents, $commented)) {
            $contents = str_replace($commented, "'content_css' => '/css/tinymce.css',", $contents);
            file_put_contents($config, $contents);
            $this->info('Enabled leap.tinymce.content_css → /css/tinymce.css');
        }
    }

    /**
     * Generate the chosen content types (--models, or the News,Event default), each via
     * `leap:content`. Runs before migrate/seed so the new migrations run. Copies the
     * shared Tag stubs first when tags are on.
     */
    protected function generateContentTypes(): void
    {
        $models = $this->resolveModels();
        if (empty($models)) {
            return;
        }

        // The registry lives in config/leap.php — leap:content appends to it.
        if (! file_exists(base_path('config/leap.php'))) {
            $this->call('vendor:publish', ['--provider' => 'NickDeKruijk\Leap\ServiceProvider', '--tag' => 'config']);
        }

        $tags = $this->confirmStep(
            'tags',
            'Add the shared tag filter to content types?',
            true,
            'One tag vocabulary across all content types, with filter chips above each overview.',
        );

        if ($tags) {
            $this->copyOrReplace('app/Models/Tag.php', 'Tag model', 'The tag itself: a translatable name, shared by every content type.');
            $this->copyOrReplace('app/Leap/Tag.php', 'Tag Leap module', 'The screen in /admin where editors manage the tag vocabulary.');
            $this->copyOrReplace('database/factories/TagFactory.php', 'Tag factory', 'Makes tags in tests and seeders.');
            $this->copyOrReplace('database/migrations/2025_01_03_094210_create_tags_table.php', 'tags table migration', 'Creates the tags table.');
            $this->copyOrReplace('database/migrations/2025_01_03_094211_create_taggables_table.php', 'taggables table migration', 'Creates the pivot that links a tag to any content type.');
        }

        foreach ($models as [$name, $archetype, $plural]) {
            $args = ['name' => $name];
            if ($archetype) {
                $args['--archetype'] = $archetype;
            }
            if ($plural) {
                $args['--plural'] = $plural;
            }
            if (! $tags) {
                $args['--no-tags'] = true;
            }
            // Propagate --force only when the installer itself is forced (--fresh/--force):
            // it bypasses leap:content's production guard and lets it overwrite. A plain
            // interactive install leaves it off, so existing generated models are kept.
            if ($this->option('force')) {
                $args['--force'] = true;
            }
            $this->call('leap:content', $args);
        }

        // leap:content leaves an already-registered type where it is, so on a re-run the
        // registry can keep a stale order. Reorder it to the requested --models order
        // (generated types first, in order; any other existing types kept after), so the
        // menu, sections and teasers follow the command.
        $this->reorderContentRegistry(array_map(
            fn (array $model): string => Str::kebab($model[2] ?: Str::plural($model[0])),
            $models
        ));
    }

    /**
     * Rewrite config('leap.content') so the given keys lead, in the given order, followed
     * by any other registered types in their current order. No-op when already ordered so
     * or when the config or its array can't be located.
     *
     * @param  array<int, string>  $keys
     */
    protected function reorderContentRegistry(array $keys): void
    {
        $path = base_path('config/leap.php');
        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);

        // The real array only — anchored so the doc-comment example ("| 'content' => [")
        // is never matched. Entries carry no brackets, so the first ] closes the array.
        if (! preg_match("/^([ \t]*)'content'\s*=>\s*\[(.*?)\n?[ \t]*\]/ms", $contents, $m)) {
            return;
        }

        preg_match_all("/^[ \t]*'([^']+)'\s*=>.*$/m", $m[2], $entries, PREG_SET_ORDER);
        if (empty($entries)) {
            return;
        }

        $byKey = [];
        foreach ($entries as $entry) {
            $byKey[$entry[1]] = trim($entry[0]);
        }

        $ordered = [];
        foreach ($keys as $key) {
            if (isset($byKey[$key])) {
                $ordered[$key] = $byKey[$key];
            }
        }
        foreach ($byKey as $key => $line) {
            $ordered[$key] ??= $line;
        }

        if (array_keys($ordered) === array_keys($byKey)) {
            return;
        }

        $indent = $m[1];
        $body = implode("\n", array_map(fn (string $line): string => $indent.'    '.$line, $ordered));
        $patched = preg_replace(
            "/^[ \t]*'content'\s*=>\s*\[.*?\n?[ \t]*\]/ms",
            $indent."'content' => [\n".$body."\n".$indent.']',
            $contents,
            1
        );

        if ($patched !== null && $patched !== $contents) {
            file_put_contents($path, $patched);
            $this->info('Reordered leap.content to match --models: '.implode(', ', array_keys($ordered)));
        }
    }

    /**
     * The content types to generate: from --models, or the interactive prompt, or the
     * News,Event default under --fresh. Each entry is [Name, archetype|null, plural|null].
     *
     * @return array<int, array{0: string, 1: ?string, 2: ?string}>
     */
    protected function resolveModels(): array
    {
        $raw = $this->option('models');
        if ($raw === null) {
            $raw = $this->option('fresh')
                ? 'News,Event'
                : text(
                    label: 'Which content types?',
                    default: 'News,Event',
                    hint: 'Comma list. The archetype is guessed from the name — news is dated, event has start/end times, anything else is hand-ordered. Override with Name:archetype, or Name:archetype:plural for a non-English plural (Bericht:news:berichten). Empty for none.',
                );
        }

        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->map(fn (string $item): array => array_pad(array_map('trim', explode(':', $item)), 3, null))
            ->values()
            ->all();
    }

    /**
     * Copy a lang/<code>.json for each chosen language.
     *
     * The views are written in English, so English needs no file — Laravel falls back to
     * the key. Every other language is a normal translation of those keys, and only the
     * ones actually chosen are worth copying, which is why this runs after the language
     * picker rather than asking about a fixed language before it.
     *
     * @param  array<int, string>  $chosen  Locale codes, in the order they were picked
     */
    protected function installTranslations(array $chosen): void
    {
        foreach ($chosen as $code) {
            if ($code === 'en') {
                continue;
            }

            if (! file_exists(__DIR__.'/../../stubs/template/lang/'.$code.'.json')) {
                $this->warn("No translations shipped for '{$code}' — the frontend strings stay English until you add lang/{$code}.json.");

                continue;
            }

            $this->copyOrReplace(
                'lang/'.$code.'.json',
                ($this->localeNames[$code] ?? $code).' translations',
                'The frontend strings (search, read more, …). Without it they stay English on the '.($this->localeNames[$code] ?? $code).' site.',
            );
        }
    }

    /**
     * Choose languages and write leap.locales + the app locale. One locale →
     * monolingual (leap.locales = null); several → an associative array.
     */
    protected function configureLocales(): void
    {
        $chosen = $this->resolveLocales();

        $this->installTranslations($chosen);

        $config = base_path('config/leap.php');
        if (! file_exists($config)) {
            $this->warn('config/leap.php not found — set leap.locales by hand.');

            return;
        }

        $contents = file_get_contents($config);
        $default = $chosen[0] ?? 'nl';

        if (count($chosen) <= 1) {
            $localesPhp = 'null';
        } else {
            $pairs = collect($chosen)
                ->map(fn (string $code): string => "'{$code}' => '".addslashes($this->localeNames[$code] ?? $code)."'")
                ->implode(', ');
            $localesPhp = "[{$pairs}]";
        }

        if (preg_match("/'locales'\s*=>\s*null/", $contents)) {
            $contents = preg_replace("/'locales'\s*=>\s*null/", "'locales' => {$localesPhp}", $contents, 1);
            file_put_contents($config, $contents);
            $this->info('Set leap.locales'.($localesPhp === 'null' ? ' (monolingual, '.$default.')' : ': '.implode(', ', $chosen)));
        } else {
            $this->warn('leap.locales is already customised — left untouched.');
        }

        $env = base_path('.env');
        if (file_exists($env)) {
            $envContents = file_get_contents($env);
            $envContents = preg_replace('/^APP_LOCALE=.*/m', 'APP_LOCALE='.$default, $envContents);
            $envContents = preg_replace('/^APP_FALLBACK_LOCALE=.*/m', 'APP_FALLBACK_LOCALE='.$default, $envContents);
            file_put_contents($env, $envContents);
            $this->info('Set APP_LOCALE / APP_FALLBACK_LOCALE to '.$default);
        }
    }

    /**
     * The chosen locale codes (first = default). From --locales, or nl only under
     * --no-multilingual / --fresh, or a multiselect with a free "other…" entry.
     *
     * @return array<int, string>
     */
    protected function resolveLocales(): array
    {
        if ($this->option('locales') !== null) {
            return $this->normaliseLocales(explode(',', $this->option('locales')));
        }
        if ($this->option('multilingual') === false || $this->option('fresh')) {
            return ['nl'];
        }

        // Pre-select whatever .env already says, so the picker agrees with the project you
        // are standing in. It only seeds the suggestion: leap.locales decides which locale
        // is unprefixed, and this writes APP_LOCALE back to match the choice.
        $current = app()->getLocale();
        if (! array_key_exists($current, $this->localeNames)) {
            $current = 'nl';
        }

        $chosen = multiselect(
            label: 'Which languages? (the first is the default)',
            options: $this->localeNames + ['other' => 'Anders…'],
            default: [$current],
            hint: 'The first one is served on / and the rest under /xx. Pick just one for a monolingual site.',
        );

        if (in_array('other', $chosen, true)) {
            $chosen = array_values(array_diff($chosen, ['other']));
            while ($code = text('Extra locale code (e.g. sv), empty to stop', hint: 'A two-letter ISO code. It is added after the languages you picked above.')) {
                $chosen[] = $code;
            }
        }

        return $this->normaliseLocales($chosen);
    }

    /**
     * Clean, de-duplicate and RTL-warn a locale list; empty → nl.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    protected function normaliseLocales(array $codes): array
    {
        $codes = collect($codes)->map(fn (string $c): string => strtolower(trim($c)))->filter()->unique()->values()->all();

        foreach (array_intersect($codes, ['ar', 'he', 'fa', 'ur']) as $rtl) {
            $this->warn("Locale [{$rtl}] is right-to-left; the template CSS has no RTL support yet.");
        }

        return $codes ?: ['nl'];
    }

    /**
     * Check the User model for the traits and contract Leap needs and print a
     * copy-paste snippet for anything missing. Does not modify the model.
     */
    protected function checkUserModel(): void
    {
        $path = base_path('app/Models/User.php');
        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        $missing = [];
        foreach ([
            'HasRoles' => 'use NickDeKruijk\Leap\Traits\HasRoles;',
            'TwoFactorAuthenticatable' => 'use Laravel\Fortify\TwoFactorAuthenticatable;',
            'PasskeyAuthenticatable' => 'use Laravel\Passkeys\PasskeyAuthenticatable;',
            'PasskeyUser' => 'use Laravel\Passkeys\Contracts\PasskeyUser; (and "implements PasskeyUser")',
        ] as $needle => $hint) {
            if (! str_contains($contents, $needle)) {
                $missing[] = $hint;
            }
        }

        if ($missing) {
            $this->newLine();
            $this->warn('Your User model (app/Models/User.php) is missing some Leap requirements. Add:');
            foreach ($missing as $line) {
                $this->line('  '.$line);
            }
            $this->line('  ...and add the traits to the class\'s "use ...;" statement.');
        }
    }

    /**
     * Offer to run the migrations and seed the sample pages.
     */
    protected function runMigrationsAndSeed(): void
    {
        if ($this->auto(
            'Run database migrations now?',
            false,
            'Runs php artisan migrate. Say no to read the migrations first, then run it yourself.',
        )) {
            // A subprocess, not $this->call(): suggestFrontendPackages() may have
            // `composer require`d packages (e.g. nickdekruijk/settings) earlier in this
            // same run. Their migrations are registered by service providers this
            // already-booted process never loaded, so an in-process migrate would leave
            // them pending. A fresh `php artisan` boots them via package discovery.
            $this->artisan(['migrate', '--force']);
        }

        if ($this->auto(
            'Seed the sample pages now?',
            false,
            'Fills the empty site with the sample content, so there is something to click on.',
        )) {
            // Also a subprocess: PageSeeder::seedContent() reads config('leap.content'),
            // which generateContentTypes() appended to config/leap.php earlier in this
            // same run. This process cached that config at boot (before the append), so an
            // in-process seed would see an empty registry and skip every content type. A
            // fresh `php artisan` reads the updated config.
            $this->artisan(['db:seed', '--class=Database\\Seeders\\PageSeeder', '--force']);
        }
    }

    /**
     * Run an artisan command in a fresh subprocess. Required for any step that follows a
     * mid-install `composer require`: this process booted before those packages existed,
     * so their service providers — and the migrations and publishable config they
     * register — are invisible to $this->call(). A fresh boot discovers them.
     */
    protected function artisan(array $arguments): void
    {
        $result = Process::path(base_path())
            ->forever()
            // Strip the inherited locale vars: this command booted before configureLocales()
            // rewrote APP_LOCALE in .env, and a subprocess inherits the parent's (stale) env,
            // which Dotenv then won't override. Removing them lets the child read .env fresh,
            // so content is seeded under the chosen locale rather than Laravel's default.
            ->env(['APP_LOCALE' => false, 'APP_FALLBACK_LOCALE' => false])
            ->run(array_merge([PHP_BINARY, base_path('artisan')], $arguments));

        $this->output->write($result->output());
        if (! $result->successful()) {
            $this->output->write($result->errorOutput());
        }
    }

    /**
     * Print the remaining manual steps once the template is installed.
     */
    protected function printNextSteps(): void
    {
        $this->newLine();
        $this->info('Template installed. Next steps:');
        $this->line('  • No asset build needed — SCSS/JS compile on request (no npm/Vite).');
        $this->line('  • Serve with a public/-rooted server (Herd/nginx), not `php artisan serve`.');

        if (! file_exists(public_path('storage'))) {
            $this->line('  • Run `php artisan storage:link` — without it no uploaded image resolves.');
        }

        $this->line('  • Create an admin user: php artisan leap:user you@example.com');
        $this->line('  • Visit /admin to manage pages, and / for the site.');
    }

    /**
     * Suggest installing the composer packages the frontend template relies on.
     * They are kept out of leap's own "require" so existing projects are never
     * forced to pull them; the template opts in here.
     *
     * minify carries a version constraint: from 4.0 its default import paths are
     * absolute and it compiles during tests, which is what the template relies on
     * instead of shipping a config of its own. On 3.x the suite would silently read
     * whatever build an earlier browser request left behind.
     */
    public function suggestFrontendPackages(): void
    {
        $packages = [
            'nickdekruijk/minify:^4.0' => 'SCSS compilation and JS bundling',
            'nickdekruijk/settings' => 'admin-editable settings + footer',
            'nickdekruijk/imageresize' => 'responsive asset_resized() images',
            'nickdekruijk/vanilla-slider' => 'carousel',
            'nickdekruijk/horizontal-scroller' => 'horizontal-scroll sections',
        ];

        $missing = array_filter(array_keys($packages), fn (string $package): bool => ! is_dir(base_path('vendor/'.Str::before($package, ':'))));

        if (empty($missing)) {
            return;
        }

        $this->info('The frontend template uses these packages:');
        foreach ($packages as $package => $why) {
            $this->line('  - '.$package.' ('.$why.')');
        }

        if ($this->auto(
            'Run "composer require" for the missing packages now?',
            true,
            'The template calls these packages. Without them the frontend errors on the first page load.',
        )) {
            $command = 'composer require '.implode(' ', $missing);
            $this->info('Running: '.$command);
            passthru($command, $status);
            if ($status === 0) {
                $this->info('Publishing settings and imageresize config...');
                // Subprocesses: the providers were just installed and are not loaded in
                // this already-booted process, so an in-process vendor:publish finds
                // nothing to publish. See artisan().
                $this->artisan(['vendor:publish', '--provider=NickDeKruijk\Settings\ServiceProvider', '--no-interaction']);
                $this->artisan(['vendor:publish', '--provider=NickDeKruijk\ImageResize\ServiceProvider', '--no-interaction']);
            }
        } else {
            $this->line('Install later with: composer require '.implode(' ', $missing));
        }
    }
}
