<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use App\Models\Page;
use App\Models\Tag;
use Illuminate\Console\OutputStyle;
use NickDeKruijk\LeapTemplate\Commands\TemplateCommand;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class ContentCommandTest extends TestCase
{
    private string $temp;

    protected function setUp(): void
    {
        parent::setUp();

        // leap:content builds on the template — it needs App\Models\Page to exist.
        if (! class_exists(Page::class)) {
            eval('namespace App\Models; class Page {}');
        }

        $this->temp = sys_get_temp_dir().'/leap-content-'.uniqid();
        foreach (['app/Models', 'app/Leap', 'database/migrations', 'database/factories', 'database/seeders', 'config'] as $dir) {
            mkdir($this->temp.'/'.$dir, 0777, true);
        }
        file_put_contents($this->temp.'/config/leap.php', "<?php\n\nreturn [\n    'content' => [],\n];\n");

        $this->app->setBasePath($this->temp);
    }

    protected function tearDown(): void
    {
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

    private function read(string $relative): string
    {
        return file_get_contents($this->temp.'/'.$relative);
    }

    public function test_generic_archetype_is_sortable_and_dateless(): void
    {
        $this->artisan('leap:content', ['name' => 'Product', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);

        $model = $this->read('app/Models/Product.php');
        $this->assertStringContainsString('class Product extends Model', $model);
        $this->assertStringContainsString("protected \$table = 'products';", $model);
        $this->assertStringContainsString("orderBy('sort')", $model);
        $this->assertStringNotContainsString('published_at', $model);
        $this->assertStringNotContainsString('HasTags', $model);

        $this->assertStringContainsString("'products' => \\App\\Models\\Product::class,", $this->read('config/leap.php'));
        // Admin nav priority is derived from the registry position, so the modules order
        // like the menu (Page first, content types in --models order, Tags last).
        $this->assertStringContainsString("array_search('products', array_keys(config('leap.content')", $this->read('app/Leap/Product.php'));
        $this->assertNotEmpty(glob($this->temp.'/database/migrations/*_create_products_table.php'));
        $this->assertFileExists($this->temp.'/app/Leap/Product.php');
        $this->assertFileExists($this->temp.'/database/factories/ProductFactory.php');
        $this->assertFileExists($this->temp.'/database/seeders/ProductSeeder.php');
    }

    public function test_template_reorders_the_registry_to_the_requested_order(): void
    {
        // A registry that is out of order (events before news, plus an unrelated type).
        file_put_contents($this->temp.'/config/leap.php', "<?php\n\nreturn [\n    'content' => [\n        'events' => \\App\\Models\\Event::class,\n        'news' => \\App\\Models\\News::class,\n        'products' => \\App\\Models\\Product::class,\n    ],\n];\n");

        $command = new TemplateCommand;
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        $method = new \ReflectionMethod($command, 'reorderContentRegistry');
        $method->invoke($command, ['news', 'events']);

        $config = $this->read('config/leap.php');
        // Requested types lead in order; the untouched one keeps its place after them.
        $this->assertLessThan(strpos($config, "'events'"), strpos($config, "'news'"));
        $this->assertLessThan(strpos($config, "'products'"), strpos($config, "'events'"));
    }

    public function test_the_registry_keeps_the_generation_order(): void
    {
        // Order matters: config('leap.content') drives the menu, section and teaser order
        // on the frontend, so it must follow the order the types were generated in.
        $this->artisan('leap:content', ['name' => 'News', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);
        $this->artisan('leap:content', ['name' => 'Event', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);
        $this->artisan('leap:content', ['name' => 'Project', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);

        $config = $this->read('config/leap.php');
        $this->assertLessThan(strpos($config, "'events'"), strpos($config, "'news'"));
        $this->assertLessThan(strpos($config, "'projects'"), strpos($config, "'events'"));
    }

    public function test_rerunning_reuses_the_migration_instead_of_duplicating_it(): void
    {
        $this->artisan('leap:content', ['name' => 'Product', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);
        $first = glob($this->temp.'/database/migrations/*_create_products_table.php');
        $this->assertCount(1, $first);

        // A second run (as leap:template --fresh does) must overwrite that migration, not
        // stack a second create-table under a fresh timestamp — which would then fail to
        // migrate with "table already exists".
        $this->artisan('leap:content', ['name' => 'Product', '--no-tags' => true, '--force' => true, '--no-interaction' => true])->assertExitCode(0);
        $second = glob($this->temp.'/database/migrations/*_create_products_table.php');
        $this->assertCount(1, $second);
        $this->assertSame($first[0], $second[0]);
    }

    public function test_news_prefix_selects_the_news_archetype(): void
    {
        $this->artisan('leap:content', ['name' => 'Newsitem', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);

        $model = $this->read('app/Models/Newsitem.php');
        $this->assertStringContainsString('published_at', $model);
        $this->assertStringContainsString("orderByDesc('published_at')", $model);
        $this->assertStringContainsString("'newsitems' => \\App\\Models\\Newsitem::class,", $this->read('config/leap.php'));
    }

    public function test_event_archetype_has_dates_and_ends_at(): void
    {
        $this->artisan('leap:content', ['name' => 'Concert', '--archetype' => 'event', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);

        $model = $this->read('app/Models/Concert.php');
        $this->assertStringContainsString('scopeFuture', $model);
        $this->assertStringContainsString('scopePast', $model);
        $this->assertStringContainsString('calculateEndsAt', $model);
        $this->assertStringContainsString('start_time', $this->read('app/Leap/Concert.php'));
    }

    public function test_explicit_plural_and_archetype_override(): void
    {
        $this->artisan('leap:content', ['name' => 'Bericht', '--archetype' => 'news', '--plural' => 'berichten', '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);

        $this->assertStringContainsString("protected \$table = 'berichten';", $this->read('app/Models/Bericht.php'));
        $this->assertStringContainsString("'berichten' => \\App\\Models\\Bericht::class,", $this->read('config/leap.php'));
        $this->assertNotEmpty(glob($this->temp.'/database/migrations/*_create_berichten_table.php'));
    }

    /**
     * Pint's laravel preset orders imports, and a scaffolded project runs pint over its own
     * app/ — so an unsorted import block in a stub leaves every generated project failing a
     * style check on a file it never wrote. Pint cannot catch this itself: it formats the
     * .php stubs in this repo, but Model.stub is not a .php file, so nothing but this test
     * stands between a hand-edited import block and that failure.
     *
     * @param  string  $type  The content type whose model stub to check
     */
    #[DataProvider('contentTypes')]
    public function test_a_generated_model_has_pint_ordered_imports(string $type, string $model): void
    {
        if (! class_exists(Tag::class)) {
            eval('namespace App\Models; class Tag {}');
        }

        $this->artisan('leap:content', ['name' => $model, '--archetype' => $type, '--no-interaction' => true])->assertExitCode(0);

        preg_match_all('/^use (.+);$/m', $this->read("app/Models/{$model}.php"), $matches);
        $imports = $matches[1];

        $this->assertNotEmpty($imports, "Expected {$model} to import something.");

        $sorted = $imports;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $imports, "The {$type} model stub's imports are not in pint's order.");
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function contentTypes(): array
    {
        return [
            'news' => ['news', 'Bulletin'],
            'event' => ['event', 'Gathering'],
            'generic' => ['generic', 'Album'],
        ];
    }

    /**
     * The stubs name package classes as plain text, so nothing but this ties them to the
     * leap version composer.json actually requires: a renamed trait, or one that only ever
     * existed on a branch, would still produce a model that reads perfectly and fatals on
     * use. The string assertions above cannot see it — they never load what they generate.
     *
     * @param  string  $type  The content type whose model stub to check
     */
    #[DataProvider('contentTypes')]
    public function test_the_package_classes_a_generated_model_imports_exist(string $type, string $model): void
    {
        if (! class_exists(Tag::class)) {
            eval('namespace App\Models; class Tag {}');
        }

        $this->artisan('leap:content', ['name' => $model, '--archetype' => $type, '--no-interaction' => true])->assertExitCode(0);

        preg_match_all('/^use (NickDeKruijk\\\\.+);$/m', $this->read("app/Models/{$model}.php"), $matches);

        $this->assertNotEmpty($matches[1], "Expected {$model} to import something from the package.");

        foreach ($matches[1] as $class) {
            $this->assertTrue(
                trait_exists($class) || class_exists($class) || interface_exists($class),
                "{$model} imports {$class}, which does not exist in the required leap.",
            );
        }
    }

    public function test_tags_block_is_kept_when_tags_are_on(): void
    {
        if (! class_exists(Tag::class)) {
            eval('namespace App\Models; class Tag {}');
        }

        $this->artisan('leap:content', ['name' => 'Album', '--no-interaction' => true])->assertExitCode(0);

        $this->assertStringContainsString('use App\Traits\HasTags;', $this->read('app/Models/Album.php'));
        $this->assertStringContainsString('->pivot(Tag::class', $this->read('app/Leap/Album.php'));
    }

    public function test_reserved_and_invalid_names_are_refused(): void
    {
        $this->artisan('leap:content', ['name' => 'Page', '--no-interaction' => true])->assertExitCode(1);
        $this->artisan('leap:content', ['name' => '123', '--no-interaction' => true])->assertExitCode(1);
    }
}
