<?php

namespace App\Providers;

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Enums\TwitterCard;
use Laravel\Head\ErrorPages;
use Laravel\Head\Facades\Head;
use Laravel\Head\Facades\Schema;
use Laravel\Head\HeadBuilder;
use Laravel\Head\Schema\Breadcrumbs;
use Laravel\Head\Schema\SchemaObject;

/**
 * Everything between <head> and </head> that is not a stylesheet or a script:
 * the title, the description, canonical, hreflang, Open Graph, X/Twitter cards
 * and the JSON-LD. laravel/head renders it, `@head` in layouts/app.blade.php is
 * where it lands.
 *
 * Three layers, lowest priority first:
 *
 * - defaults(), below. Site-wide and request-independent — it is evaluated once
 *   while the app boots, so nothing here may read the locale, the database or the
 *   request.
 * - the view composer at the bottom, which is where anything per-request goes. It
 *   fires just before the layout renders, so the page being looked at is already
 *   resolved and so is the locale.
 * - errors(), which wins over both on an error response.
 *
 * One trap, and it is a test-only one: laravel/head holds its per-page data in a
 * container-scoped object, which a web request gets fresh and a test does not. Two
 * calls to $this->get() inside one test method therefore share a head, and the
 * second page is rendered on top of the first one's title and JSON-LD. One request
 * per test, or call Head::flush() in between.
 */
class HeadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerDefaults();
        $this->registerErrorPages();
        $this->registerPageMetadata();
    }

    /**
     * Site-wide, and the same for every visitor on every URL.
     *
     * No title suffix: a page hands over the finished string from
     * HasDocumentMeta::documentTitle(), which appends the site name itself and is
     * free not to when html_title says so. A suffix here would append it twice.
     */
    protected function registerDefaults(): void
    {
        Head::defaults(fn (HeadBuilder $head) => $head
            ->title(config('app.name'))
            // forceHttps is off so the canonical follows the scheme the site is
            // actually served over -- on http://…​.test that is what a developer can
            // click, and in production the request is https anyway. No trailing slash,
            // so the homepage is canonically https://example.com and not …​.com/, which
            // is a second address for the same page.
            ->canonical(forceHttps: false, trailingSlash: false)
            ->og(type: OgType::Website, siteName: config('app.name'))
            ->twitter(card: TwitterCard::Summary)
            ->viewport('width=device-width, initial-scale=1')
            ->colorScheme('light dark')
            ->favicon('/favicon.ico')
            // Fill these in per project and drop the ones you have no file for -- a
            // <link> to an icon that is not there is a 404 on every page load.
            // ->icon('/icon.svg', type: ImageType::Svg)
            // ->appleTouchIcon('/apple-touch-icon.png', sizes: '180x180')
            // ->manifest('/site.webmanifest')
            // ->themeColor('#ffffff', media: Media::Light)
            // ->themeColor('#111827', media: Media::Dark)
        );
    }

    /**
     * An error page answers on whatever URL was asked for, and it has a canonical
     * pointing at itself -- so without noindex a scanner can have one empty page
     * indexed per URL it invents. "follow", not "none": the links out of it are the
     * way back into the site.
     *
     * No title per status. This runs while the app boots, so __() would translate
     * against the boot locale and hand a Dutch visitor an English title; the pages
     * keep the site name they already had.
     */
    protected function registerErrorPages(): void
    {
        Head::errors(fn (ErrorPages $errors) => $errors->defaults(robots: 'noindex, follow'));
    }

    /**
     * Everything that depends on the request: the page's own title and description,
     * the locale, the alternates, the social image and the structured data.
     *
     * A view composer rather than a call in PageController, for two reasons. It fires
     * for the error views too, which no controller of ours reaches. And Blade passes a
     * child view's data up into its layout, so $page, $type and $parent are all here
     * without anything having to thread them through.
     */
    protected function registerPageMetadata(): void
    {
        View::composer('layouts.app', function ($view): void {
            $data = $view->getData();
            $page = $data['page'] ?? null;

            Head::og(
                url: url()->current(),
                locale: str_replace('_', '-', app()->getLocale()),
            );

            if ($page) {
                Head::title($page->documentTitle())
                    ->alternates(static::alternates($page));

                if ($description = $page->metaDescription()) {
                    Head::description($description);
                }
            }

            if ($image = static::socialImage($page)) {
                Head::ogImage($image)->twitter(card: TwitterCard::SummaryWithLargeImage);
            }

            foreach ([
                static::itemSchema($page, $data['type'] ?? null),
                static::breadcrumbSchema($page, $data['parent'] ?? null),
            ] as $schema) {
                if ($schema) {
                    Head::schema($schema);
                }
            }
        });
    }

    /**
     * hreflang: the same page in every locale it has a slug in. Empty on a
     * monolingual site, and on a view with no page of its own.
     *
     * @return array<string, string>
     */
    protected static function alternates(mixed $page): array
    {
        return collect(PageController::localeUrls($page))
            ->mapWithKeys(fn (array $alternate, string $locale): array => [$locale => url($alternate['url'])])
            ->all();
    }

    /**
     * The og:image: the page's own image or its first section image, else the
     * site-wide og_image setting. An error page has no page of its own and lands on
     * the setting, which is the right picture for "somewhere on this site".
     */
    protected static function socialImage(mixed $page): ?string
    {
        if ($image = $page?->ogImageUrl()) {
            return $image;
        }

        if (! function_exists('setting') || ! $setting = setting('og_image')) {
            return null;
        }

        return str_starts_with($setting, 'http') ? $setting : url($setting);
    }

    /**
     * JSON-LD for a content detail page. The type comes from the content archetype:
     * an event has a scopeFuture, a news key means an article, anything else is a
     * CreativeWork.
     *
     * Returns the object rather than registering it, so a test can read it without
     * rendering a page.
     */
    public static function itemSchema(mixed $page, ?string $type): ?SchemaObject
    {
        if (! $page || ! $type) {
            return null;
        }

        $isEvent = method_exists($page::class, 'scopeFuture');

        $schema = Schema::make($isEvent ? 'Event' : (str_contains($type, 'news') ? 'NewsArticle' : 'CreativeWork'))
            ->set('name', $page->title)
            ->set('headline', $page->title)
            ->set('url', url()->current());

        if ($description = $page->metaDescription()) {
            $schema->set('description', $description);
        }

        if ($image = $page->media?->first()?->url) {
            $schema->set('image', $image);
        }

        if (! $isEvent && ! empty($page->published_at)) {
            $schema->set('datePublished', $page->published_at->toIso8601String());
        }

        if ($isEvent && ! empty($page->date)) {
            $schema->set('startDate', $page->date->toDateString());
        }

        if ($isEvent && ! empty($page->ends_at)) {
            $schema->set('endDate', $page->ends_at->toIso8601String());
        }

        return $schema;
    }

    /**
     * BreadcrumbList, from the very same trail components/breadcrumbs.blade.php
     * shows, so what a search engine reads and what a visitor sees cannot drift
     * apart. Not emitted for a one-step trail, which is the homepage.
     *
     * A ListItem needs an item on every step but the last, so an ancestor with no URL
     * -- untranslated in this locale -- is left out rather than written without one,
     * and the positions close up behind it.
     *
     * Added one at a time rather than through items(), which keys the list on the name:
     * two ancestors that happen to share a title would collapse into one step.
     */
    public static function breadcrumbSchema(mixed $page, mixed $parent = null): ?Breadcrumbs
    {
        $crumbs = PageController::breadcrumbs($page, $parent);

        if ($crumbs->count() < 2) {
            return null;
        }

        $schema = Schema::breadcrumbs();

        $crumbs->slice(0, -1)
            ->filter(fn (array $crumb): bool => (bool) $crumb['url'])
            ->push($crumbs->last())
            // The last crumb has no link of its own; it is the page being looked at.
            ->each(fn (array $crumb) => $schema->item($crumb['title'], $crumb['url'] ? url($crumb['url']) : url()->current()));

        return $schema;
    }
}
