<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Finder\Finder;

/**
 * The stubs are the product, and nothing else here parses them: they are copied as text,
 * so a syntax error ships green and only surfaces in someone's project. A duplicate import
 * introduced while editing one of these got past a full suite run.
 *
 * .stub files are templates ({{ Model }} where a class name goes), so they are checked with
 * their placeholders filled in — which is the only form that ever reaches a project.
 */
class StubsParseTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stubs(): array
    {
        $files = Finder::create()
            ->files()
            ->in(__DIR__.'/../../stubs')
            ->name(['*.php', '*.stub']);

        $cases = [];
        foreach ($files as $file) {
            $cases[$file->getRelativePathname()] = [$file->getPathname()];
        }

        return $cases;
    }

    #[DataProvider('stubs')]
    public function test_a_stub_is_valid_php(string $path): void
    {
        // Blade is not PHP, and neither is a half-templated class name.
        if (str_ends_with($path, '.blade.php')) {
            $this->markTestSkipped('Blade, not PHP.');
        }

        $code = (string) file_get_contents($path);

        // A {{#tags}} block is rendered two ways (ContentCommand::render): the inner text
        // kept, or the whole block dropped. Only one of those used to be checked, so a stub
        // could be broken with --no-tags and nothing would say so.
        foreach (['with tags' => '$1', 'without tags' => ''] as $variant => $replacement) {
            $rendered = preg_replace('/\{\{#tags\}\}(.*?)\{\{\/tags\}\}/s', $replacement, $code);

            $this->assertValidPhp((string) $rendered, basename($path).' ('.$variant.')');
        }
    }

    private function assertValidPhp(string $code, string $what): void
    {
        $code = str_replace(
            ['{{ Model }}', '{{ Models }}', '{{ model }}', '{{ models }}', '{{ table }}', '{{ key }}', '{{ plural }}'],
            ['Thing', 'Things', 'thing', 'things', 'things', 'things', 'things'],
            $code
        );

        $file = tempnam(sys_get_temp_dir(), 'stub').'.php';
        file_put_contents($file, $code);
        exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);
        unlink($file);

        $this->assertSame(0, $status, $what.' is not valid PHP: '.implode("\n", $output));
    }
}
