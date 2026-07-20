<?php

namespace Tests\Feature;

use App\Leap\Page as PageResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use Tests\TestCase;

/**
 * The section definitions an editor sees in the admin.
 *
 * Leap resolves a per-locale array for label() and hint(), but not for the options of a
 * select: `components/select.blade.php` echoes each one straight into the template, so an
 * array reaches htmlspecialchars() and takes the whole editor down with a TypeError. The
 * mistake has been made twice — once on the events `period` field, once on `layout` — and
 * neither showed up until someone opened a page that used that section.
 */
class SectionAttributeTest extends TestCase
{
    // The tag select reads the tags table, so the schema has to exist.
    use RefreshDatabase;

    public function test_every_select_option_is_a_string(): void
    {
        foreach ($this->sections() as $section) {
            foreach ($section->attributes as $attribute) {
                foreach ($attribute->getValues() as $key => $label) {
                    $this->assertTrue(
                        is_string($label) || is_null($label),
                        "Section [{$section->name}], attribute [{$attribute->name}], option [{$key}] is a ".gettype($label).
                        '. Leap prints a select option as-is, so a per-locale array crashes the editor — translate it with __() instead.',
                    );
                }
            }
        }
    }

    /**
     * Every section the page resource offers, hand-typed and generated alike.
     *
     * Reached through the attribute tree rather than by calling indexSections(), which is
     * protected: this is the same shape the admin renders, so a section that only exists
     * for one content type is covered too.
     *
     * @return array<int, Section>
     */
    private function sections(): array
    {
        $found = [];

        foreach ((new PageResource)->attributes() as $attribute) {
            if ($attribute instanceof Attribute && $attribute->type === 'sections') {
                $found = array_merge($found, $attribute->sections);
            }
        }

        $this->assertNotEmpty($found, 'Expected the page resource to offer sections');

        return $found;
    }
}
