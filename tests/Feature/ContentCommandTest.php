<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use App\Models\Page;
use App\Models\Tag;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

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
        $this->assertNotEmpty(glob($this->temp.'/database/migrations/*_create_products_table.php'));
        $this->assertFileExists($this->temp.'/app/Leap/Product.php');
        $this->assertFileExists($this->temp.'/database/factories/ProductFactory.php');
        $this->assertFileExists($this->temp.'/database/seeders/ProductSeeder.php');
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
