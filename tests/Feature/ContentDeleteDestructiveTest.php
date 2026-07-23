<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * --drop-table is the irreversible half of leap:content-delete. The file and
 * registry side is covered elsewhere; what happens in the database was not, and
 * every one of these leaves something behind if it silently stops working: a
 * pivot pointing at rows that are gone, a page listing a type that no longer
 * exists, or a migrations row that makes migrate:status lie.
 */
class ContentDeleteDestructiveTest extends TestCase
{
    private string $temp;

    protected function setUp(): void
    {
        parent::setUp();

        // leap:content refuses to run without App\Models\Page, so it has to exist
        // before the first generate() — not only by the time the tables are built.
        require_once dirname(__DIR__).'/Fixtures/app-models-page.php';

        $this->temp = sys_get_temp_dir().'/leap-content-destructive-'.uniqid();
        foreach (['app/Models', 'app/Leap', 'database/migrations', 'database/factories', 'database/seeders', 'config'] as $dir) {
            mkdir($this->temp.'/'.$dir, 0777, true);
        }
        file_put_contents($this->temp.'/config/leap.php', "<?php\n\nreturn [\n    'content' => [],\n];\n");

        $this->app->setBasePath($this->temp);

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
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

    /**
     * A generated type as it exists in a running project: its own table, the
     * polymorphic tag pivot, the page that lists it and the migrations row.
     */
    private function buildProject(string $table = 'projects'): void
    {
        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('title');
        });
        DB::table($table)->insert(['title' => 'A project']);

        Schema::create('taggables', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('tag_id');
            $blueprint->string('taggable_type');
            $blueprint->unsignedBigInteger('taggable_id');
        });
        DB::table('taggables')->insert([
            ['tag_id' => 1, 'taggable_type' => 'App\Models\Project', 'taggable_id' => 1],
            ['tag_id' => 1, 'taggable_type' => 'App\Models\News', 'taggable_id' => 1],
        ]);

        Schema::create('pages', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('title');
            $blueprint->text('sections')->nullable();
            $blueprint->softDeletes();
        });
        DB::table('pages')->insert([
            ['title' => 'Projects', 'sections' => json_encode([['_name' => 'projects']]), 'deleted_at' => null],
            ['title' => 'About', 'sections' => json_encode([['_name' => 'default']]), 'deleted_at' => null],
        ]);

        Schema::create('migrations', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('migration');
            $blueprint->integer('batch');
        });
        DB::table('migrations')->insert([
            ['migration' => '2026_01_01_000000_create_projects_table', 'batch' => 1],
            ['migration' => '2026_01_01_000001_create_news_table', 'batch' => 1],
        ]);
    }

    private function generate(string $name): void
    {
        $this->artisan('leap:content', ['name' => $name, '--no-tags' => true, '--no-interaction' => true])
            ->assertExitCode(0);
    }

    private function deleteType(string $name): void
    {
        $this->artisan('leap:content-delete', [
            'name' => $name,
            '--drop-table' => true,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);
    }

    public function test_drop_table_removes_the_types_own_table(): void
    {
        $this->generate('Project');
        $this->buildProject();

        $this->assertTrue(Schema::hasTable('projects'));

        $this->deleteType('Project');

        $this->assertFalse(Schema::hasTable('projects'));
    }

    /**
     * Tag links live in a polymorphic pivot, so they survive the table being
     * dropped and would point at rows that no longer exist. Only this type's rows
     * go — another type's links must be untouched.
     */
    public function test_only_this_types_tag_links_are_removed(): void
    {
        $this->generate('Project');
        $this->buildProject();

        $this->deleteType('Project');

        $this->assertSame(0, DB::table('taggables')->where('taggable_type', 'App\Models\Project')->count());
        $this->assertSame(1, DB::table('taggables')->where('taggable_type', 'App\Models\News')->count());
    }

    /**
     * The overview page is force-deleted rather than soft-deleted: a page left in
     * the bin still holds the slug, so re-generating the type cannot reuse it.
     */
    public function test_the_overview_page_is_force_deleted_not_soft_deleted(): void
    {
        $this->generate('Project');
        $this->buildProject();

        $this->deleteType('Project');

        $this->assertSame(0, DB::table('pages')->where('title', 'Projects')->count());
        $this->assertSame(1, DB::table('pages')->where('title', 'About')->count());
    }

    public function test_a_page_listing_another_type_is_left_alone(): void
    {
        $this->generate('Project');
        $this->buildProject();

        DB::table('pages')->insert([
            'title' => 'News',
            'sections' => json_encode([['_name' => 'news']]),
            'deleted_at' => null,
        ]);

        $this->deleteType('Project');

        $this->assertSame(1, DB::table('pages')->where('title', 'News')->count());
    }

    /**
     * The migration file is deleted along with everything else, so leaving its row
     * behind makes migrate:status report a migration that no longer exists.
     */
    public function test_the_migrations_row_goes_with_the_file(): void
    {
        $this->generate('Project');
        $this->buildProject();

        $this->deleteType('Project');

        $this->assertSame(0, DB::table('migrations')->where('migration', 'like', '%_create_projects_table')->count());
        $this->assertSame(1, DB::table('migrations')->where('migration', 'like', '%_create_news_table')->count());
    }

    /**
     * Without --drop-table the data stays put: the files and the registry entry go,
     * but nothing in the database is touched.
     */
    public function test_without_drop_table_the_database_is_untouched(): void
    {
        $this->generate('Project');
        $this->buildProject();

        $this->artisan('leap:content-delete', [
            'name' => 'Project',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('projects'));
        $this->assertSame(1, DB::table('pages')->where('title', 'Projects')->count());
        $this->assertSame(1, DB::table('taggables')->where('taggable_type', 'App\Models\Project')->count());
    }

    /**
     * A project without the tag pivot or a Page model — leap:template was never
     * run, or tags were declined — must not make the command fail.
     */
    public function test_a_project_without_tags_or_pages_still_deletes_cleanly(): void
    {
        $this->generate('Project');

        Schema::create('projects', function (Blueprint $blueprint): void {
            $blueprint->id();
        });

        $this->deleteType('Project');

        $this->assertFalse(Schema::hasTable('projects'));
    }
}
