<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Controllers\ModuleController;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Role;
use Tests\Concerns\ReadsTheSiteLanguage;
use Tests\TestCase;

/**
 * The editor's preview: the frontend of one record, for the person editing it.
 *
 * It exists because a page that is switched off, or written in a language this site does
 * not serve yet, has no address a visitor could reach — and someone still has to be able
 * to look at it before it goes live.
 *
 * What matters just as much is what it does not do. It renders one record by id; it
 * relaxes no scope. So while a preview is open, that same page still answers 404 on its
 * own URL, stays out of the menu and stays out of the sitemap.
 */
class PreviewTest extends TestCase
{
    use ReadsTheSiteLanguage;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PageSeeder::class);
    }

    /**
     * Someone with read on the pages module, which is exactly what a preview asks for.
     */
    private function actingAsEditor(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::find(1));

        $this->actingAs($user);
    }

    private function pageModule(): string
    {
        return (new \App\Leap\Page)->getSlug();
    }

    private function previewUrl(int $id, ?string $locale = null): string
    {
        return route('leap.preview', array_filter([
            'module' => $this->pageModule(),
            'id' => $id,
            'locale' => $locale,
        ]));
    }

    /**
     * The model guards mass assignment, so the flag is set the long way round.
     */
    private function switchOff(Page $page): void
    {
        $page->active = false;
        $page->save();
    }

    private function page(): Page
    {
        $page = Page::active()->get()->first(fn (Page $page): bool => ! $page->parent && $page->slug !== '/');

        $this->assertNotNull($page, 'Expected at least one non-home root page from the seeder');

        return $page;
    }

    public function test_a_visitor_cannot_preview(): void
    {
        $this->get($this->previewUrl($this->page()->id))->assertRedirect(route('leap.login'));
    }

    public function test_an_ordinary_page_previews_as_itself(): void
    {
        $this->actingAsEditor();
        $page = $this->page();

        $preview = $this->get($this->previewUrl($page->id))->assertOk();

        $preview->assertSee($page->title, false);
        $preview->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    /**
     * The headline case, and the reason both halves are asserted together: previewing a
     * page that is switched off must not make it reachable anywhere else.
     */
    public function test_a_switched_off_page_previews_while_its_own_url_stays_a_404(): void
    {
        $this->actingAsEditor();
        $page = $this->page();
        $slug = $page->slug;
        $this->switchOff($page);

        $this->get($this->previewUrl($page->id))
            ->assertOk()
            ->assertSee($page->title, false);

        $this->get('/'.$slug)->assertNotFound();
    }

    /**
     * And it stays out of the navigation while its preview renders: the menu is built
     * from Page::active(), which the preview never touches.
     */
    public function test_a_switched_off_page_is_not_in_the_menu_of_its_own_preview(): void
    {
        $this->actingAsEditor();
        $page = $this->page();
        $page->menuitem = true;
        $this->switchOff($page);

        $html = $this->get($this->previewUrl($page->id))->assertOk()->getContent();

        // Its title as a menu link, not its URL: the footer links to a few pages by hand
        // and the language switcher offers this very page in each language, and neither is
        // built from the menu query this is about.
        $nav = Str::between($html, '<nav class="nav"', '</nav>');

        $this->assertStringNotContainsString('>'.$page->title.'</a>', $nav);
    }

    public function test_a_previewed_page_stays_out_of_the_sitemap(): void
    {
        $this->actingAsEditor();
        $page = $this->page();
        $this->switchOff($page);

        $this->get($this->previewUrl($page->id))->assertOk();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/'.$page->slug.'</loc>', false);
    }

    /**
     * A language being written but not served has no URLs at all — its prefix falls
     * through to a 404 like any unknown path. The preview is the only way to read it.
     */
    public function test_a_language_the_site_does_not_publish_previews(): void
    {
        if (! config('leap.locales') || count(config('leap.locales')) < 2) {
            $this->markTestSkipped('The site has fewer than two languages, so none can be unpublished.');
        }

        $locales = array_keys(config('leap.locales'));
        $unpublished = end($locales);
        config(['leap.locales_published' => array_values(array_diff($locales, [$unpublished]))]);

        $this->actingAsEditor();
        $page = $this->page();

        $this->assertArrayNotHasKey($unpublished, Leap::localesPublished());

        $this->get($this->previewUrl($page->id, $unpublished))
            ->assertOk()
            ->assertSee($page->getTranslation('title', $unpublished), false);

        $this->get('/'.$unpublished.'/'.$page->getTranslation('slug', $unpublished))->assertNotFound();
    }

    /**
     * Previewing German must not leave the editor's own browsing in German: the preview
     * renders the view directly and never reaches the route that remembers a language.
     */
    public function test_previewing_does_not_change_the_remembered_language(): void
    {
        if (! config('leap.locales') || count(config('leap.locales')) < 2) {
            $this->markTestSkipped('The site is monolingual.');
        }

        $this->actingAsEditor();
        $page = $this->page();
        $locales = array_keys(config('leap.locales'));

        $this->get($this->previewUrl($page->id, end($locales)))->assertOk();

        $this->assertNull(session('leap_locale'));
    }

    public function test_a_content_item_previews_through_its_own_view(): void
    {
        $models = collect(PageController::indexModels())
            ->filter(fn (string $model, string $type): bool => (bool) PageController::overviewPage($type));

        if ($models->isEmpty()) {
            $this->markTestSkipped('No content types with an overview page are installed.');
        }

        $this->actingAsEditor();

        $type = $models->keys()->first();
        $model = $models->first();
        $item = $model::active()->first();

        if (! $item) {
            $this->markTestSkipped('No seeded '.$type.' item to preview.');
        }

        $module = collect(ModuleController::getAllModules())
            ->first(fn ($module): bool => method_exists($module, 'getModel') && $module->getModel()::class === $model);

        $this->assertNotNull($module, 'Expected a leap module for '.$model);

        $this->get(route('leap.preview', ['module' => $module->getSlug(), 'id' => $item->id]))
            ->assertOk()
            ->assertSee($item->title, false);
    }
}
