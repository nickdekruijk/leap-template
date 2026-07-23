<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\LeapTemplate\Tests\Concerns\BuildsTempApp;
use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * `leap:template --diff` reports how a project stands next to the current template.
 * A sha1 per file only covers half of that: a release that adds a column or a
 * translation key leaves every file it has alone, and the project finds out through an
 * admin that will not save or a Dutch page in English. Those two, plus the install
 * steps that leave no trace in a file at all, are what these tests are about.
 */
class TemplateDiffTest extends TestCase
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
     * The output of a --diff run, as one string.
     */
    private function diff(): string
    {
        // Artisan::call() rather than $this->artisan(): a report is read as a whole here,
        // not asserted line by line, and only this way is the buffer ours to read.
        $this->assertSame(0, Artisan::call('leap:template', ['--diff' => true]));

        return Artisan::output();
    }

    /**
     * A pages table as it stood before a release added a column to the stub migration.
     */
    private function pagesTable(bool $withBreadcrumb): void
    {
        Schema::create('pages', function (Blueprint $table) use ($withBreadcrumb) {
            $table->id();
            $table->unsignedBigInteger('parent')->nullable();
            $table->boolean('active')->default(1);
            $table->datetime('published_at')->nullable();
            $table->boolean('menuitem')->default(1);
            if ($withBreadcrumb) {
                $table->boolean('breadcrumb')->default(1);
            }
            foreach (['title', 'html_title', 'slug', 'description', 'video_id', 'sections', 'meta'] as $column) {
                $table->json($column)->nullable();
            }
            $table->unsignedInteger('sort')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * The case this was built for: an existing project takes the new app/Leap/Page.php
     * and its admin stops saving, because the create-table migration only ever runs on a
     * fresh install and nothing else mentions the column.
     */
    public function test_a_column_a_later_release_added_is_reported(): void
    {
        $this->pagesTable(withBreadcrumb: false);

        $output = $this->diff();

        $this->assertStringContainsString('pages has no breadcrumb column', $output);
        // The stub's own line, so the migration can be copied out of the report.
        $this->assertStringContainsString("\$table->boolean('breadcrumb')->default(1);", $output);
        $this->assertStringContainsString('make:migration add_breadcrumb_to_pages_table', $output);
    }

    public function test_a_table_that_has_every_column_is_not_reported(): void
    {
        $this->pagesTable(withBreadcrumb: true);

        $this->assertStringNotContainsString('has no breadcrumb column', $this->diff());
    }

    /**
     * morphs('taggable') makes taggable_type and taggable_id, and neither is a column
     * called "taggable" — reading the stub literally reported a column that can never
     * exist. An index is no column either.
     */
    public function test_a_morphs_column_is_read_as_the_two_columns_it_makes(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->unsignedBigInteger('tag_id');
            $table->morphs('taggable');
        });

        $output = $this->diff();

        $this->assertStringNotContainsString('taggables has no', $output);
    }

    /**
     * No table is an unanswered question, not drift: a project that never migrated, or a
     * --diff run with no database behind it, gets a report rather than an exception.
     */
    public function test_a_project_without_its_tables_still_gets_a_report(): void
    {
        $output = $this->diff();

        $this->assertStringNotContainsString('has no breadcrumb column', $output);
        $this->assertStringContainsString('unchanged', $output);
    }

    /**
     * A lang file is not a copy of the stub and is not meant to become one: the site has
     * its own strings, and `lang:add` merges Laravel's whole vocabulary into it. Only the
     * one direction is drift — a key the template added that this project never got.
     * A unified diff of the same file was 290 lines of the project's own translations.
     */
    public function test_a_translation_file_reports_missing_keys_rather_than_a_diff(): void
    {
        $stub = json_decode(file_get_contents(dirname(__DIR__, 2).'/stubs/template/lang/nl.json'), true);
        $dropped = array_key_first($stub);
        unset($stub[$dropped]);

        // A hundred strings of its own, the way lang:add leaves it.
        for ($i = 0; $i < 100; $i++) {
            $stub['Framework string '.$i] = 'Kaderstring '.$i;
        }

        mkdir($this->temp.'/lang', 0777, true);
        file_put_contents($this->temp.'/lang/nl.json', json_encode($stub, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $output = $this->diff();

        $this->assertStringContainsString('template strings missing', $output);
        $this->assertStringContainsString($dropped, $output);
        // Its own hundred are not drift but property.
        $this->assertStringNotContainsString('Kaderstring 0', $output);

        // And it stays a handful of lines: the unified diff this replaces ran to 290 for
        // the same file, all of them the project's own strings.
        $block = substr($output, strpos($output, 'changed: lang/nl.json'));
        $block = substr($block, 0, strpos($block, "\n\n"));
        $this->assertLessThan(6, substr_count($block, "\n"), 'A lang file must not flood the report.');
    }

    public function test_a_translation_file_that_has_every_key_says_so_in_one_line(): void
    {
        $stub = json_decode(file_get_contents(dirname(__DIR__, 2).'/stubs/template/lang/nl.json'), true);
        $stub['Framework string'] = 'Kaderstring';

        mkdir($this->temp.'/lang', 0777, true);
        file_put_contents($this->temp.'/lang/nl.json', json_encode($stub, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->assertStringContainsString('template strings are there', $this->diff());
    }

    /**
     * The steps that leave no trace in a template file: a project whose files are
     * perfectly up to date can still have no catch-all route and no storage link, and
     * both fail quietly — / is a 404, an uploaded image does not resolve.
     */
    public function test_the_install_steps_are_checked_too(): void
    {
        $output = $this->diff();

        // The skeleton has Laravel's welcome route and neither of the template's.
        $this->assertStringContainsString('✗ the catch-all route', $output);
        $this->assertStringContainsString('✗ the sitemap route', $output);
        $this->assertStringContainsString('✗ public/storage', $output);
        // Its .gitignore has one of the two build rules, so the other is named.
        $this->assertStringContainsString('/public/js/builds', $output);
    }

    public function test_a_step_that_is_in_place_costs_one_line_and_no_advice(): void
    {
        file_put_contents(
            $this->temp.'/routes/web.php',
            "<?php\n\nRoute::get('{any}', [PageController::class, 'route'])->where('any', '(.*)');\n",
        );

        $output = $this->diff();

        $this->assertStringContainsString('✓ the catch-all route', $output);
        $this->assertStringNotContainsString('✗ the catch-all route', $output);
    }

    /**
     * Every file is diffed against itself. The project path was computed in the loop that
     * sorts the files and reused in the loop that prints them, so it had long since become
     * whichever file that first loop ended on — `lang/nl.json`, since the conditional
     * files are appended last. Every "changed:" heading was then followed by a diff of
     * that one file against the stub being reported, which reads as the whole file being
     * rewritten and buries the real change.
     */
    public function test_each_changed_file_is_diffed_against_itself(): void
    {
        // Two changed files, so a leftover path cannot coincide with the right one, plus
        // the lang file that made it the last one standing.
        file_put_contents($this->temp.'/app/Models/Page.php', "<?php\n\n// PROJECT MARKER: models page\n");
        file_put_contents($this->temp.'/app/Http/Controllers/PageController.php', "<?php\n\n// PROJECT MARKER: controller\n");
        mkdir($this->temp.'/lang', 0777, true);
        file_put_contents($this->temp.'/lang/nl.json', json_encode(['Search' => 'Zoeken']));

        $output = $this->diff();

        // Each heading must be followed by a unified diff naming that same file.
        preg_match_all('/^changed: (\S+)$/m', $output, $matches);
        $this->assertNotEmpty($matches[1], 'Expected changed files to report.');

        foreach ($matches[1] as $relative) {
            if (str_starts_with($relative, 'lang/')) {
                continue; // Reported as missing keys, not as a diff.
            }

            $block = substr($output, strpos($output, 'changed: '.$relative));
            $header = strtok(substr($block, strpos($block, '--- ')), "\n");

            $this->assertStringContainsString(
                $relative,
                $header,
                "The diff under \"changed: {$relative}\" is of another file: {$header}",
            );
        }
    }

    /**
     * A report that changed the project would be a trap: this is what you run to decide
     * whether to upgrade, in a project that is deliberately customised.
     */
    public function test_diff_changes_nothing(): void
    {
        $this->pagesTable(withBreadcrumb: false);

        $before = $this->fingerprint($this->temp);
        $this->diff();

        $this->assertSame($before, $this->fingerprint($this->temp));
    }

    /**
     * Every file under a directory with its hash, so a write of any kind shows up.
     *
     * @return array<string, string>
     */
    private function fingerprint(string $dir): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[$file->getPathname()] = sha1_file($file->getPathname());
            }
        }

        ksort($files);

        return $files;
    }
}
