<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use Database\Seeders\PageSeeder;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use ReflectionMethod;

/**
 * What shape the page seeder writes.
 *
 * The seeds carry every language, and forSite() cuts them down to what the site has. On a
 * multilingual site that is a per-locale array. On a monolingual one it has to be a plain
 * string: that is what leap stores without leap.locales configured, and it used to seed
 * the array shape regardless — which showed up as "[object Object]" in every section field
 * of the editor, because with no locales the editor binds an input straight to the stored
 * value.
 *
 * The stub is the product here and is never autoloaded, so it is required by path.
 */
class SeederLocaleShapeTest extends TestCase
{
    private function forSite(mixed $data): mixed
    {
        require_once __DIR__.'/../../stubs/template/database/seeders/PageSeeder.php';

        $seeder = new PageSeeder;

        return (new ReflectionMethod($seeder, 'forSite'))->invoke($seeder, $data);
    }

    public function test_a_monolingual_site_gets_a_plain_string(): void
    {
        config(['leap.locales' => null]);
        $this->app->setLocale('nl');

        $this->assertSame('Welkom', $this->forSite(['nl' => 'Welkom', 'en' => 'Welcome']));
    }

    public function test_a_monolingual_site_falls_back_to_english_then_to_the_first_entry(): void
    {
        config(['leap.locales' => null]);
        $this->app->setLocale('de');

        $this->assertSame('Welcome', $this->forSite(['nl' => 'Welkom', 'en' => 'Welcome']));
        $this->assertSame('Welkom', $this->forSite(['nl' => 'Welkom', 'fr' => 'Bienvenue']));
    }

    public function test_a_multilingual_site_still_gets_one_entry_per_configured_locale(): void
    {
        config(['leap.locales' => ['nl' => 'Nederlands', 'en' => 'English']]);

        $this->assertSame(
            ['nl' => 'Welkom', 'en' => 'Welcome'],
            $this->forSite(['nl' => 'Welkom', 'en' => 'Welcome', 'de' => 'Willkommen']),
        );
    }

    /**
     * The translation sets sit inside the sections, so the walk has to reach them — and
     * leave everything that is not a translation set exactly as it was.
     */
    public function test_it_reaches_translation_sets_nested_in_sections(): void
    {
        config(['leap.locales' => null]);
        $this->app->setLocale('nl');

        $seeded = $this->forSite([
            'title' => ['nl' => 'Home', 'en' => 'Home'],
            'sort' => 1,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Kop', 'en' => 'Heading'],
                    'image_position' => 'left',
                ],
            ],
        ]);

        $this->assertSame('Home', $seeded['title']);
        $this->assertSame('Kop', $seeded['sections'][0]['head']);
        $this->assertSame(1, $seeded['sort']);
        $this->assertTrue($seeded['sections'][0]['active']);
        $this->assertSame('left', $seeded['sections'][0]['image_position']);
    }
}
