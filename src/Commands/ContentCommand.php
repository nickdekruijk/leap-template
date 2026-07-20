<?php

namespace NickDeKruijk\LeapTemplate\Commands;

use App\Models\Page;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Generate a listed content type for the frontend template: a model, a Leap resource,
 * a migration, a factory and a seeder, then register it in config('leap.content').
 *
 * Three archetypes decide the shape. The name is free — the archetype is guessed from
 * the name prefix (News*, Event*) or given with --archetype:
 *
 *   news     chronological, published_at required (future = staged/hidden)
 *   event    + date and start/end time, published_at optional, upcoming/past
 *   generic  hand-ordered (sort), no dates — Project, Product, Artist, Album …
 */
class ContentCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The `'content' => [ ... ]` array in config/leap.php, capturing its indent and its
     * body. Anchored to the start of a line with only whitespace before the key, so the
     * example inside the doc comment above it — prefixed with "| " — is never matched
     * instead. Non-greedy, so it stops at the first closing bracket on its own line, and
     * it matches an empty `'content' => []` as well.
     *
     * Public because ContentDeleteCommand unregisters with the same expression that this
     * one registers with — two copies would drift, and a delete that misses its entry
     * leaves the registry pointing at a class that no longer exists.
     */
    public const CONTENT_ARRAY = "/^([ \t]*)'content'\s*=>\s*\[(.*?)\n?[ \t]*\]/ms";

    protected $signature = 'leap:content {name : Singular StudlyCase name, e.g. News, Product}
        {--archetype= : news|event|generic (default: guessed from the name)}
        {--plural= : Override the plural used for the table and registry key (Str::plural is English, and so should the name be)}
        {--no-tags : Leave out the shared Tag relation and filter}
        {--force : Overwrite existing files}';

    protected $description = 'Generate a listed content type (model, resource, migration, factory, seeder) and register it';

    /**
     * Reserved model names that would collide with the template's own classes.
     *
     * @var array<int, string>
     */
    protected array $reserved = ['Page', 'Tag', 'User'];

    /**
     * Seconds added to the migration timestamp per file, so several types generated in
     * the same second (the template's install loop) still get ordered filenames.
     */
    protected static int $migrationOffset = 0;

    public function handle(): int
    {
        // A scaffolding command — never on production without --force.
        if (! $this->confirmToProceed()) {
            return 1;
        }

        // The generator builds on the template (Page + the content engine + registry).
        if (! class_exists(Page::class)) {
            $this->error('leap:content needs the frontend template. Run `php artisan leap:template` first, or use `php artisan leap:module` for an admin-only resource.');

            return 1;
        }

        $name = Str::studly($this->argument('name'));

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error("Invalid name [{$name}] — use a StudlyCase singular like News or Product.");

            return 1;
        }

        if (in_array($name, $this->reserved, true)) {
            $this->error("[{$name}] is reserved by the template.");

            return 1;
        }

        $archetype = $this->option('archetype') ?: $this->guessArchetype($name);
        if (! in_array($archetype, ['news', 'event', 'generic'], true)) {
            $this->error("Unknown archetype [{$archetype}] — use news, event or generic.");

            return 1;
        }

        // Str::plural is English, and so is the name: a content type is code — a class, a
        // table, a registry key — never a URL. Those come from the slug of the page that
        // lists the type, per locale, so a Dutch site is /berichten with a News model. That
        // is also why there is no question here: the guess is right whenever the name is
        // English, and naming it in English is the point.
        $plural = $this->option('plural') ?: Str::plural($name);

        $tags = ! $this->option('no-tags') && class_exists(Tag::class);

        $tokens = [
            '{{ Model }}' => $name,
            '{{ model }}' => Str::camel($name),
            '{{ Models }}' => Str::studly($plural),
            '{{ models }}' => Str::camel($plural),
            '{{ table }}' => Str::snake($plural),
            '{{ key }}' => Str::kebab($plural),
        ];

        // Idempotent: a re-run (a second leap:template) must not fail on what the first
        // run built. Skip when the model already exists, unless --force.
        if (class_exists("App\\Models\\{$name}") && ! $this->option('force')) {
            $this->components->info("Content type [{$name}] already exists — skipping (use --force to overwrite).");
            $this->registerInConfig($tokens['{{ key }}'], $name);

            return 0;
        }

        $files = [
            'Model.stub' => "app/Models/{$name}.php",
            'Resource.stub' => "app/Leap/{$name}.php",
            'factory.stub' => "database/factories/{$name}Factory.php",
            'seeder.stub' => "database/seeders/{$name}Seeder.php",
            'migration.stub' => 'database/migrations/'.$this->migrationName($tokens['{{ table }}']),
        ];

        foreach ($files as $stub => $target) {
            $this->render($archetype, $stub, base_path($target), $tokens, $tags);
        }

        $this->registerInConfig($tokens['{{ key }}'], $name);

        $this->components->info("Generated content type [{$name}] ({$archetype}".($tags ? '' : ', no tags').').');

        return 0;
    }

    protected function guessArchetype(string $name): string
    {
        return match (true) {
            Str::startsWith($name, 'News') => 'news',
            Str::startsWith($name, 'Event') => 'event',
            default => 'generic',
        };
    }

    /**
     * The migration filename for a table. Reuses an existing
     * `*_create_{table}_table.php` so re-running (e.g. `leap:template --fresh`) overwrites
     * it in place — otherwise a second create-table migration under a fresh timestamp
     * stacks up and `migrate` fails with "table already exists". Only new tables get a
     * fresh, per-file timestamp.
     */
    protected function migrationName(string $table): string
    {
        $existing = glob(base_path("database/migrations/*_create_{$table}_table.php"));
        if ($existing) {
            return basename($existing[0]);
        }

        return Carbon::now()->addSeconds(static::$migrationOffset++)->format('Y_m_d_His')."_create_{$table}_table.php";
    }

    /**
     * Render a stub: resolve the {{#tags}} blocks, replace the tokens, tidy blank
     * lines, and write it (creating directories as needed).
     *
     * @param  array<string, string>  $tokens
     */
    protected function render(string $archetype, string $stub, string $target, array $tokens, bool $tags): void
    {
        $content = file_get_contents(__DIR__."/../../stubs/content/{$archetype}/{$stub}");

        // Keep the inner text of {{#tags}}…{{/tags}} blocks, or drop the whole block.
        $content = preg_replace('/\{\{#tags\}\}(.*?)\{\{\/tags\}\}/s', $tags ? '$1' : '', $content);

        $content = strtr($content, $tokens);

        // Collapse the blank lines a dropped block may leave behind.
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        if (file_exists($target) && ! $this->option('force')) {
            $this->components->twoColumnDetail($target, '<fg=yellow>exists, skipped</>');

            return;
        }

        if (! is_dir($dir = dirname($target))) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($target, $content);
        $this->components->twoColumnDetail(str_replace(base_path().'/', '', $target), '<fg=green>created</>');
    }

    /**
     * Append the type to config('leap.content'). No-op when already there. When the
     * config isn't published, print the line to add by hand instead of failing.
     */
    protected function registerInConfig(string $key, string $model): void
    {
        $path = base_path('config/leap.php');
        $line = "        '{$key}' => \\App\\Models\\{$model}::class,";

        if (! file_exists($path)) {
            $this->warn("config/leap.php not found — add this to leap.content by hand:\n{$line}");

            return;
        }

        $contents = file_get_contents($path);

        // Already registered? The key is what decides that, not how the class behind it
        // is written: a config that imports the model and says `'news' => News::class`
        // means the same as the fully qualified form written below, and matching only
        // the latter appended a duplicate on every run. PHP resolves duplicate keys to
        // the last one silently, so this piled up unnoticed.
        //
        // Looked for inside the content array only — the doc comment above it carries an
        // example with the same shape, and matching that would skip a real registration.
        if (preg_match(static::CONTENT_ARRAY, $contents, $found)
            && preg_match("/^\s*'".preg_quote($key, '/')."'\s*=>/m", $found[2])) {
            return;
        }

        // Append as the LAST entry of the 'content' => [ ... ] array, so the registry
        // (and thus the menu, section and teaser order downstream) follows the order the
        // types were generated in — `--models=News,Event` lists news before events.
        // Anchored to the start of a line with only indentation before the key, so the
        // `'content' => [` in the doc-comment example (prefixed with "| ") is never
        // matched instead. Handles the empty `'content' => []` too, and rebuilds the
        // whole array so the closing bracket stays on its own line.
        $patched = preg_replace_callback(
            static::CONTENT_ARRAY,
            function (array $m) use ($line): string {
                $indent = $m[1];
                $existing = ltrim(rtrim($m[2]), "\n");
                $body = $existing === '' ? $line : $existing."\n".$line;

                return "{$indent}'content' => [\n{$body}\n{$indent}]";
            },
            $contents,
            1,
            $count
        );

        if ($count && $patched !== $contents) {
            file_put_contents($path, $patched);
            $this->components->twoColumnDetail("config/leap.php → leap.content['{$key}']", '<fg=green>registered</>');
        } else {
            $this->warn("Could not find leap.content in config/leap.php — add this by hand:\n{$line}");
        }
    }
}
