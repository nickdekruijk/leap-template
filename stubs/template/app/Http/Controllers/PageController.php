<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Leap;

class PageController extends Controller
{
    /**
     * The listed content types, keyed by their route/section slug. The registry lives
     * in config('leap.content'); the class_exists guard keeps a registered-but-removed
     * class harmless. This is the single map the whole engine (routing, overviews,
     * Page sections, search, sitemap) is built on.
     *
     * @return array<string, class-string<Model>>
     */
    public static function indexModels(): array
    {
        return array_filter(config('leap.content', []), 'class_exists');
    }

    /**
     * The model for a content-type key, or null.
     *
     * @return class-string<Model>|null
     */
    public static function indexModel(?string $type): ?string
    {
        return static::indexModels()[$type] ?? null;
    }

    /**
     * The overview page for a content type: the page whose section of that type lists
     * *every* item — no limit, and no tag narrowing it down. Detail pages live under
     * it (/news/{slug}), so each item has exactly one URL. Returns null when no such
     * page exists (an editor removed it), which the engine treats as "no detail pages,
     * cards without a link" rather than an error.
     */
    public static function overviewPage(string $type): ?Page
    {
        return once(function () use ($type): ?Page {
            foreach (Page::active()->get() as $page) {
                foreach ($page->sections ?: [] as $section) {
                    if (($section['_name'] ?? null) !== $type || ! ($section['active'] ?? true)) {
                        continue;
                    }

                    if (empty($section['limit']) && empty($section['tag'])) {
                        return $page;
                    }
                }
            }

            return null;
        });
    }

    /**
     * The items an index section lists, each carrying the URL of its detail page.
     *
     * @return Collection<int, Model>
     */
    public static function sectionItems(mixed $section): Collection
    {
        return static::items(
            $section['_name'] ?? null,
            // A row lists everything unless the editor picked a tag for it.
            $section['tag'] ?? null,
            $section['limit'] ?? null,
            period: $section['period'] ?? null,
        );
    }

    /**
     * The items of a content type, optionally narrowed to a tag, cut off at a limit,
     * and (for events) filtered by period. Each item carries the URL of its detail
     * page — or null when the type has no overview page.
     *
     * @return Collection<int, Model>
     */
    public static function items(?string $type, int|string|null $tag = null, int|string|null $limit = null, bool $withTags = false, ?string $period = null): Collection
    {
        $model = static::indexModel($type);

        if (! $model) {
            return collect();
        }

        // A card is a picture, a title and an intro. Skip the heavy sections blob so an
        // overview of hundreds of items does not drag every item's body HTML along.
        $columns = array_values(array_diff(Schema::getColumnListing((new $model)->getTable()), ['sections', 'meta']));

        $base = static::itemsQuery($model, $columns, $tag, $withTags);

        // Events: upcoming (default), past, or both. "both" = upcoming ascending, then
        // past descending, so the newest-finished sits right under the soonest.
        if (method_exists($model, 'scopeFuture')) {
            if ($period === 'past') {
                $items = static::itemsQuery($model, $columns, $tag, $withTags)->past()->reorder()->orderByDesc('ends_at')->limit($limit ?: null)->get();
            } elseif ($period === 'both') {
                $items = $base->future()->get()->concat(
                    static::itemsQuery($model, $columns, $tag, $withTags)->past()->reorder()->orderByDesc('ends_at')->get()
                );
                if ($limit) {
                    $items = $items->take((int) $limit);
                }
            } else {
                $items = $base->future()->limit($limit ?: null)->get();
            }
        } else {
            $items = $base->limit($limit ?: null)->get();
        }

        $overview = static::overviewPage($type);
        $prefix = $overview ? Leap::localePrefix().static::localePath($overview, app()->getLocale()) : null;

        return $items->each(function (Model $item) use ($prefix): void {
            $item->url = $prefix ? rtrim($prefix, '/').'/'.$item->slug : null;
        });
    }

    /**
     * The base query for a content type's cards: active, light columns, first image,
     * optional tag narrowing and eager-loaded tags for the filter chips.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $columns
     */
    protected static function itemsQuery(string $model, array $columns, int|string|null $tag, bool $withTags)
    {
        $query = $model::active()
            ->select($columns)
            ->with(['media' => fn (MorphToMany $media) => $media->wherePivot('mediable_attribute', 'images')]);

        if ($withTags && method_exists($model, 'tags')) {
            $query->with('tags');
        }

        if (! empty($tag) && method_exists($model, 'scopeTagged')) {
            $query->tagged((int) $tag);
        }

        return $query;
    }

    /**
     * The tags to offer above a full listing: those actually carried by the items on
     * show, so a chip never leads to an empty grid — minus the tag the section is
     * already fixed to. Empty when tags are not installed.
     *
     * @param  Collection<int, Model>  $items
     * @return Collection<int, Model>
     */
    public static function sectionTags(Collection $items, int|string|null $exclude = null): Collection
    {
        return $items->pluck('tags')
            ->filter()
            ->flatten()
            ->unique('id')
            ->reject(fn (Model $tag): bool => $exclude !== null && (int) $tag->id === (int) $exclude)
            ->sortBy('sort')
            ->values();
    }

    /**
     * Load the active pages grouped by parent id (segment-independent). getPages()
     * memoizes this per request via once(); pages are few, so there is no persistent
     * cache to invalidate.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected static function loadPages(): array
    {
        $pages = [];

        // Get all pages but only the attributes we need for navigation
        $attributes = ['id', 'title', 'slug', 'parent', 'menuitem', 'sections'];
        foreach (Page::active()->get($attributes) as $page) {
            // Only keep section titles that are flagged as a menu item, to add them as in-page
            // anchors later. A section 'head' is translatable, so on a multilingual site it is a
            // per-locale array; this reads the raw sections cast rather than HasSections, so it
            // has to resolve that itself — the menu renders the title as a string.
            if (isset($page->sections)) {
                $menuitemTitles = [];
                foreach (collect($page->sections)->where('menuitem', 1)->sortBy('_sort') as $menuitem) {
                    $head = $menuitem['head'] ?? '';
                    // Missing in the active locale: fall back to the first translation there is,
                    // as HasSections does, so a half-translated heading still reads.
                    $menuitemTitles[] = is_array($head) ? ($head[app()->getLocale()] ?? (reset($head) ?: '')) : $head;
                }
                $page->sections = $menuitemTitles;
            }

            $data = $page->only($attributes);
            // Resolve the slug without laravel-translatable's fallback. A slug is an address, not
            // prose: a page with no slug in the active locale must read as empty (not routable
            // there) rather than borrow another locale's. Reading it as an attribute would fall
            // back to config('app.fallback_locale'), so on a site whose fallback is one of its own
            // languages an untranslated page would answer on the other locale's URL — while
            // buildLocalePath() (the sitemap) already asks without the fallback and leaves it out.
            $data['slug'] = (string) $page->getTranslation('slug', app()->getLocale(), false);
            $pages[$page->parent ?: 0][] = $data;
        }

        return $pages;
    }

    /**
     * Get all active pages, structured so we can build the navigation and resolve
     * the current page. Memoized for the duration of the request via once().
     *
     * @return array<string, mixed>
     */
    public static function getPages(array $segments = []): array
    {
        return once(function () use ($segments) {
            $pages = static::loadPages();
            $pages['menu'] = [];
            $pages['current'] = null;

            // URL prefix for the active locale (empty for the default/only locale)
            $prefix = Leap::localePrefix();

            // Traverse the pages to find the active page and build the menu
            $traverse = function (array &$pages, array $segments = [], int $parent = 0, int $depth = 0, bool $activeParent = true, string $path = '') use (&$traverse, $prefix) {
                foreach ($pages[$parent] ?? [] as $page) {
                    // If the page is a menu item, add it to the menu array
                    if ($page['menuitem']) {
                        $pages['menu'][$parent][$page['id']] = $page;
                        $pages['menu'][$parent][$page['id']]['url'] = $prefix.(rtrim($path.'/'.$page['slug'], '/') ?: '/');
                    }
                    // Add in-page anchor links for sections flagged as menu items
                    foreach ($page['sections'] ?: [] as $i => $section) {
                        $pages['menu'][$page['id']][$i] = ['title' => $section];
                        $pages['menu'][$page['id']][$i]['url'] = $prefix.$path.'/'.$page['slug'].'#'.Str::slug($section);
                    }

                    // The homepage is the page whose slug is "/", not simply the first page (order-independent).
                    // Pages without a slug in the active locale (empty) are not routable, so never match.
                    $active = $activeParent && isset($segments[$depth]) && $page['slug'] !== '' && ($segments[$depth] === $page['slug'] || ($segments[$depth] == '' && $page['slug'] == '/'));
                    if ($active) {
                        $pages['active'][$page['id']] = true;
                        // If the active page is the last segment, it is the current page
                        if ($depth == count($segments) - 1) {
                            $pages['current'] = $page;
                        }
                    }

                    // Traverse further if the page has children
                    if (isset($pages[$page['id']])) {
                        $traverse($pages, $segments, $page['id'], $depth + 1, $active, $path.'/'.$page['slug']);
                    }
                }
            };
            $traverse($pages, $segments);

            return $pages;
        });
    }

    /**
     * Get only the menu part of the getPages result.
     *
     * @return array<int|string, mixed>
     */
    public static function getMenu(int $parent = 0): array
    {
        return static::getPages()['menu'][$parent] ?? [];
    }

    /**
     * Frontend catch-all route.
     */
    public function route(?string $uri = null): View
    {
        $segments = explode('/', $uri ?: '');

        // When multilingual, strip and apply a leading locale prefix (gated on leap.locales)
        Leap::detectLocale($segments);

        // A bare locale prefix ("/en") leaves no segments; the homepage matches an empty one
        if (empty($segments)) {
            $segments = [''];
        }

        $pages = static::getPages($segments);

        // No page by that name? The last segment may be a content item hanging under
        // its overview page — /news/{slug}.
        if (! $pages['current'] && count($segments) > 1) {
            return $this->routeItem($segments);
        }

        abort_if(! $pages['current'], 404);

        $page = Page::find($pages['current']['id']);

        return view('page', compact('page'));
    }

    /**
     * Detail page of a content item: the segments up to the last one must resolve to
     * that type's overview page (see overviewPage()), the last one is the item's slug
     * in the active locale.
     */
    protected function routeItem(array $segments): View
    {
        $slug = array_pop($segments);

        $pages = static::getPages($segments);
        abort_if(! $pages['current'], 404);

        $parent = Page::find($pages['current']['id']);

        foreach (static::indexModels() as $type => $model) {
            if (! static::overviewPage($type)?->is($parent)) {
                continue;
            }

            $item = $model::active()->where('slug->'.app()->getLocale(), $slug)->first();
            abort_if(! $item, 404);

            return view('item', [
                'page' => $item,
                'type' => $type,
                'parent' => $parent,
                'parentUrl' => Leap::localePrefix().static::localePath($parent, app()->getLocale()),
            ]);
        }

        abort(404);
    }

    /**
     * XML sitemap: the page tree plus every registered content type's items.
     */
    public function sitemap(): Response
    {
        return response()
            ->view('sitemap', ['urls' => static::sitemapEntries()])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Sitemap entries for the page tree and every content item. When multilingual,
     * every entry gets one URL per locale it has a routable slug for, each carrying
     * hreflang alternates.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    public static function sitemapEntries(): Collection
    {
        $pages = Page::active()->get(['id', 'slug', 'parent', 'updated_at']);
        $map = $pages->keyBy('id');
        $locales = config('leap.locales');

        $urls = collect();

        // Pages
        foreach ($pages as $page) {
            $urls = $urls->concat(static::entriesFor(
                fn (string $locale) => static::buildLocalePath($page, $locale, $map),
                $page->updated_at,
                $locales,
            ));
        }

        // Content items, each under its overview page (skipped when it has none)
        foreach (static::indexModels() as $type => $model) {
            $overview = static::overviewPage($type);
            if (! $overview) {
                continue;
            }

            foreach ($model::active()->get() as $item) {
                $urls = $urls->concat(static::entriesFor(
                    function (string $locale) use ($overview, $item): ?string {
                        $slug = $item->getTranslation('slug', $locale, false) ?: '';

                        return $slug === '' ? null : rtrim(static::localePath($overview, $locale), '/').'/'.$slug;
                    },
                    $item->updated_at,
                    $locales,
                ));
            }
        }

        return $urls->values();
    }

    /**
     * One sitemap entry per locale a path resolves in, with hreflang alternates.
     * Monolingual sites get a single alternate-less entry.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates: array<string, string>}>
     */
    protected static function entriesFor(callable $path, $lastmod, ?array $locales): Collection
    {
        if (! $locales) {
            $p = $path(app()->getLocale());

            return $p === null ? collect() : collect([[
                'loc' => url($p),
                'lastmod' => $lastmod?->toAtomString(),
                'alternates' => [],
            ]]);
        }

        $hrefs = collect(array_keys($locales))
            ->mapWithKeys(fn (string $locale): array => [$locale => $path($locale)])
            ->filter(fn (?string $p): bool => $p !== null)
            ->map(fn (string $p, string $locale): string => url(Leap::localePrefix($locale).$p));

        return $hrefs->map(fn (string $loc): array => [
            'loc' => $loc,
            'lastmod' => $lastmod?->toAtomString(),
            'alternates' => $hrefs->all(),
        ])->values();
    }

    /**
     * URLs for the given page or content item in each configured locale, for a
     * language switcher. Empty unless multilingual.
     *
     * @return array<string, array{name: string, url: string, active: bool}>
     */
    public static function localeUrls(Page|Model|null $page): array
    {
        $locales = config('leap.locales');
        if (! $locales || ! $page) {
            return [];
        }

        $urls = [];
        foreach ($locales as $locale => $name) {
            $path = $page instanceof Page
                ? static::localePath($page, $locale)
                : static::itemLocalePath($page, $locale);

            $urls[$locale] = [
                'name' => $name,
                'url' => Leap::localePrefix($locale).$path,
                'active' => $locale === app()->getLocale(),
            ];
        }

        return $urls;
    }

    /**
     * A content item's full URL in the active locale, or null when its type has no
     * overview page (so no detail route). Used by search and any linking code.
     */
    public static function itemUrl(Model $item): ?string
    {
        $type = array_search($item::class, static::indexModels(), true);
        if (! $type || ! static::overviewPage($type)) {
            return null;
        }

        return Leap::localePrefix().static::itemLocalePath($item, app()->getLocale());
    }

    /**
     * A content item's path in a specific locale: its overview page's path in that
     * locale, plus the item's own slug translation.
     */
    protected static function itemLocalePath(Model $item, string $locale): string
    {
        $type = array_search($item::class, static::indexModels(), true);
        $overview = $type ? static::overviewPage($type) : null;

        if (! $overview) {
            return '/';
        }

        $slug = $item->getTranslation('slug', $locale, false) ?: '';

        return rtrim(static::localePath($overview, $locale), '/').'/'.$slug;
    }

    /**
     * Build a page's path in a specific locale by walking the parent chain.
     */
    protected static function localePath(Page $page, string $locale): string
    {
        $slug = $page->getTranslation('slug', $locale, false) ?: '';
        $path = $page->parent && ($parent = Page::find($page->parent))
            ? static::localePath($parent, $locale).'/'.$slug
            : '/'.$slug;

        return rtrim($path, '/') ?: '/';
    }

    /**
     * Build a page's path in a specific locale using a preloaded id => Page map
     * (no N+1 queries), returning null if the page — or any ancestor in its
     * chain — has no slug translation for the locale.
     *
     * @param  Collection<int, Page>  $map
     */
    protected static function buildLocalePath(Page $page, string $locale, Collection $map): ?string
    {
        $slug = $page->getTranslation('slug', $locale, false) ?: '';
        if ($slug === '') {
            return null;
        }

        if (! $page->parent) {
            return rtrim('/'.$slug, '/') ?: '/';
        }

        $parent = $map->get($page->parent);
        $parentPath = $parent ? static::buildLocalePath($parent, $locale, $map) : null;

        return $parentPath !== null
            ? (rtrim($parentPath.'/'.$slug, '/') ?: '/')
            : null;
    }
}
