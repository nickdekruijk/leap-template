<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Commands\TemplateCommand;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

class TemplateManifestTest extends TestCase
{
    /**
     * Files that only ship when the project asked for them: the tag filter (--no-tags leaves it
     * out) and the per-language translations. templateFiles() adds these only when they already
     * exist in the project, and a bare testbench app has neither.
     *
     * @var array<int, string>
     */
    private const CONDITIONAL = [
        'app/Traits/HasTags.php',
        'app/Models/Tag.php',
        'app/Leap/Tag.php',
        'database/factories/TagFactory.php',
        'database/migrations/2025_01_03_094210_create_tags_table.php',
        'database/migrations/2025_01_03_094211_create_taggables_table.php',
    ];

    /**
     * Stubs that are shown rather than copied. routes/web.php is never written over — the
     * installer appends the sitemap and catch-all routes to whatever the project already has —
     * so the stub is a picture of the end state, and drift on it would be noise.
     *
     * @var array<int, string>
     */
    private const NOT_COPIED = [
        'routes/web.php',
    ];

    public function test_template_files_manifest_matches_the_shipped_stubs(): void
    {
        $files = $this->templateFiles();
        $stubBase = $this->stubBase();

        $this->assertNotEmpty($files, 'templateFiles() returned no files.');

        foreach ($files as $relative) {
            $this->assertFileExists(
                $stubBase.'/'.$relative,
                "leap:template lists \"{$relative}\" but no matching stub ships under stubs/template.",
            );
        }
    }

    /**
     * And the other way round, which is the direction that actually bit: a stub no list mentions
     * is never copied, so it ships green here and fatals in someone's project. That is how
     * tests/Concerns/ResolvesContentPaths.php was missed — SeoTest uses the trait, the trait
     * stayed behind, and `php artisan test` died on the first file it read.
     */
    public function test_every_shipped_stub_is_listed_for_installation(): void
    {
        $stubBase = $this->stubBase();
        $listed = array_flip($this->templateFiles());

        foreach (Finder::create()->files()->in($stubBase)->notName('.DS_Store') as $file) {
            $relative = $file->getRelativePathname();

            if (in_array($relative, self::CONDITIONAL, true)
                || in_array($relative, self::NOT_COPIED, true)
                || str_starts_with($relative, 'lang/')) {
                continue;
            }

            $this->assertArrayHasKey(
                $relative,
                $listed,
                "stubs/template/{$relative} ships but templateFiles() never lists it, so leap:template leaves it behind.",
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function templateFiles(): array
    {
        return (new ReflectionMethod(TemplateCommand::class, 'templateFiles'))->invoke(new TemplateCommand);
    }

    private function stubBase(): string
    {
        return dirname(__DIR__, 2).'/stubs/template';
    }
}
