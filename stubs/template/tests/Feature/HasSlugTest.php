<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\Page;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_slug_is_generated_from_the_title(): void
    {
        $page = Page::forceCreate(['title' => 'About Our Company', 'active' => true]);

        $this->assertSame('about-our-company', $page->fresh()->slug);
    }

    public function test_sibling_pages_with_the_same_title_get_a_unique_slug(): void
    {
        $first = Page::forceCreate(['title' => 'Services', 'active' => true]);
        $second = Page::forceCreate(['title' => 'Services', 'active' => true]);

        $this->assertSame('services', $first->fresh()->slug);
        $this->assertNotSame('services', $second->fresh()->slug);
        $this->assertStringStartsWith('services-', $second->fresh()->slug);
    }

    public function test_the_homepage_slug_is_never_slugified(): void
    {
        $page = Page::forceCreate(['title' => 'Home', 'slug' => '/', 'active' => true]);

        $this->assertSame('/', $page->fresh()->slug);
    }

    /**
     * Slugs are generated on the saving event, so a DatabaseSeeder using Laravel's
     * WithoutModelEvents seeded every content item with a null slug: its detail page was
     * unreachable, its card linked to "/news/", and it dropped out of the sitemap. Pages
     * escaped it — PageSeeder writes their slugs literally — which is what kept it quiet.
     */
    public function test_seeding_the_database_gives_every_content_item_a_slug(): void
    {
        $models = PageController::indexModels();
        if (empty($models)) {
            $this->markTestSkipped('Installed without content types.');
        }

        $this->seed(DatabaseSeeder::class);

        foreach ($models as $model) {
            $this->assertGreaterThan(0, $model::count(), class_basename($model).' seeded nothing to check.');

            foreach ($model::all() as $item) {
                $this->assertNotEmpty(
                    $item->slug,
                    class_basename($model).' #'.$item->id.' was seeded without a slug.',
                );
            }
        }
    }
}
