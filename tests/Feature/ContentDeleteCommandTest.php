<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * leap:content generated five files, a registry entry, a table and the page that lists
 * it, and nothing took any of that back. A typo in `leap:template --models=…` therefore
 * cost an afternoon of hand-editing — and missing one of the six left a registry
 * pointing at a class that no longer existed.
 */
class ContentDeleteCommandTest extends TestCase
{
    private string $temp;

    protected function setUp(): void
    {
        parent::setUp();

        // Shared with ContentDeleteDestructiveTest: the first definition in the
        // process wins, so both tests have to load the same one.
        require_once dirname(__DIR__).'/Fixtures/app-models-page.php';

        $this->temp = sys_get_temp_dir().'/leap-content-delete-'.uniqid();
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

    private function generate(string $name): void
    {
        $this->artisan('leap:content', ['name' => $name, '--no-tags' => true, '--no-interaction' => true])->assertExitCode(0);
    }

    /**
     * @return array<int, string>
     */
    private function filesFor(string $name, string $table): array
    {
        return [
            "app/Models/{$name}.php",
            "app/Leap/{$name}.php",
            "database/factories/{$name}Factory.php",
            "database/seeders/{$name}Seeder.php",
            ...array_map(
                fn (string $path): string => 'database/migrations/'.basename($path),
                glob($this->temp."/database/migrations/*_create_{$table}_table.php") ?: [],
            ),
        ];
    }

    public function test_it_removes_every_file_the_generator_wrote(): void
    {
        $this->generate('Product');

        foreach ($this->filesFor('Product', 'products') as $path) {
            $this->assertFileExists($this->temp.'/'.$path);
        }

        $files = $this->filesFor('Product', 'products');

        $this->artisan('leap:content-delete', ['name' => 'Product', '--force' => true, '--no-interaction' => true])->assertExitCode(0);

        foreach ($files as $path) {
            $this->assertFileDoesNotExist($this->temp.'/'.$path, $path.' survived the delete');
        }
    }

    public function test_it_unregisters_the_type_and_leaves_the_others_in_order(): void
    {
        $this->generate('News');
        $this->generate('Event');
        $this->generate('Product');

        $this->artisan('leap:content-delete', ['name' => 'Event', '--force' => true, '--no-interaction' => true])->assertExitCode(0);

        $config = $this->read('config/leap.php');

        $this->assertStringNotContainsString("'events' =>", $config);
        $this->assertLessThan(
            strpos($config, "'products' =>"),
            strpos($config, "'news' =>"),
            'The remaining types should keep the order they were generated in',
        );
    }

    /**
     * The last entry is where a freshly generated type lands, so it is the one a typo
     * needs taken back — and the one a line-removal that insists on a trailing newline
     * silently leaves behind, because the array's closing bracket took that newline.
     */
    public function test_it_unregisters_the_last_entry_in_the_array(): void
    {
        $this->generate('News');
        $this->generate('Product');

        $this->artisan('leap:content-delete', ['name' => 'Product', '--force' => true, '--no-interaction' => true])->assertExitCode(0);

        $config = $this->read('config/leap.php');

        $this->assertStringNotContainsString("'products' =>", $config);
        $this->assertStringContainsString("'news' =>", $config);
    }

    /**
     * Str::plural('Events') is 'Events', so a stray type generated from the plural form
     * derives to the real Event's table and registry key. Deleting it must not take the
     * type that got there first with it.
     */
    public function test_it_leaves_the_registry_alone_when_the_key_belongs_to_another_model(): void
    {
        $this->generate('Event');

        file_put_contents($this->temp.'/app/Models/Events.php', "<?php\n\nnamespace App\\Models;\n\nclass Events {}\n");
        file_put_contents($this->temp.'/app/Leap/Events.php', "<?php\n\nnamespace App\\Leap;\n\nclass Events {}\n");

        $migration = glob($this->temp.'/database/migrations/*_create_events_table.php')[0];

        $this->artisan('leap:content-delete', ['name' => 'Events', '--force' => true, '--no-interaction' => true])->assertExitCode(0);

        // The stray files go…
        $this->assertFileDoesNotExist($this->temp.'/app/Models/Events.php');
        $this->assertFileDoesNotExist($this->temp.'/app/Leap/Events.php');

        // …but everything the real Event owns stays.
        $this->assertFileExists($migration);
        $this->assertFileExists($this->temp.'/app/Models/Event.php');
        $this->assertStringContainsString("'events' =>", $this->read('config/leap.php'));
    }

    public function test_a_reserved_name_is_refused(): void
    {
        $this->artisan('leap:content-delete', ['name' => 'Page', '--force' => true, '--no-interaction' => true])->assertExitCode(1);
    }

    public function test_an_unknown_type_ends_quietly(): void
    {
        $this->artisan('leap:content-delete', ['name' => 'Nonexistent', '--force' => true, '--no-interaction' => true])->assertExitCode(0);
    }

    /**
     * A non-interactive run without --force must not treat "no TTY" as consent: the delete
     * is destructive (files removed, pages force-deleted), so it has to refuse and keep
     * the generated files in place.
     */
    public function test_a_non_interactive_run_without_force_keeps_the_files(): void
    {
        $this->generate('Product');
        $files = $this->filesFor('Product', 'products');

        $this->artisan('leap:content-delete', ['name' => 'Product', '--no-interaction' => true]);

        foreach ($files as $path) {
            $this->assertFileExists($this->temp.'/'.$path, $path.' was deleted without --force');
        }
    }
}
