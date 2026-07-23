<?php

namespace App\Leap;

use App\Http\Controllers\PageController;
use App\Leap\Concerns\ContentSections;
use App\Models\Tag;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Classes\Attribute;
use NickDeKruijk\Leap\Classes\Section;
use NickDeKruijk\Leap\Resource;

class Page extends Resource
{
    use ContentSections;

    public function attributes()
    {
        return [
            Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
            Attribute::make('menuitem')->index(3)->switch()->label(['nl' => 'Toon in navigatie', 'en' => 'Show in navigation'], 'Nav')->default(true),
            Attribute::make('breadcrumb')->switch()->label(['nl' => 'Toon kruimelpad', 'en' => 'Show breadcrumb'])->default(true)
                ->hint(['nl' => 'Het pad naar deze pagina, met een terug-link. De homepage toont er nooit een: die is zelf de enige stap.', 'en' => 'The path to this page, with a back link. The homepage never shows one: it is the only step there is.']),
            Attribute::make('title')->index(1)->searchable()->required()->label(['nl' => 'Titel', 'en' => 'Title']),
            Attribute::make('slug')->index()->searchable()->unique()->slugFrom('title')->label('Slug')
                ->hint(['nl' => 'Het laatste deel van de URL, achter het pad van eventuele bovenliggende pagina\'s. Leeg laten leidt \'m automatisch van de titel af.', 'en' => 'The last part of the URL, appended after the path of any parent pages. Leave empty to derive it from the title automatically.']),
            Attribute::make('parent')->tree($this)->label(['nl' => 'Subpagina van', 'en' => 'Subpage of']),
            Attribute::make('html_title')->searchable()
                ->label(['nl' => 'HTML-titel', 'en' => 'HTML title'])
                ->placeholder(['nl' => 'Leeg = paginatitel', 'en' => 'Empty = page title'])
                ->hint(['nl' => 'Voor SEO: de titel in de browsertab en zoekresultaten. Leeg laten gebruikt de paginatitel.', 'en' => 'For SEO: the title in the browser tab and search results. Leave empty to use the page title.']),
            Attribute::make('description')->textarea()
                ->label(['nl' => 'Omschrijving', 'en' => 'Description'])
                ->hint(['nl' => 'Voor SEO: de meta-omschrijving voor Google en social media (±150 tekens).', 'en' => 'For SEO: the meta description for Google and social media (~150 characters).']),
            Attribute::make('id')->indexOnly(),
            Attribute::make('sort')->sortable(),
            Attribute::make('images')->media(),
            Attribute::make('sections')->label(['nl' => 'Secties', 'en' => 'Sections'])->sections(
                ...$this->contentSections(),
                ...$this->indexSections(),
            ),
        ];
    }

    /**
     * A card-row section per registered content type (config('leap.content')). Each
     * lets an editor drop a row of that type's cards onto any page — a limited teaser
     * that links to the overview, or the full overview itself. The section key is the
     * content key, so the frontend renders it through sections/{key}.blade.php when
     * that exists (the dated events view) or the generic sections/items.blade.php.
     *
     * @return array<int, Section>
     */
    protected function indexSections(): array
    {
        $sections = [];

        foreach (PageController::indexModels() as $key => $model) {
            $view = View::exists("sections.$key") ? "sections.$key" : 'sections.items';

            $attributes = [
                Attribute::make('active')->switch()->label(['nl' => 'Actief', 'en' => 'Active'])->default(true),
                Attribute::make('head')->label(['nl' => 'Kop', 'en' => 'Heading'])->sectionTitle()->translatable(),
            ];

            // Events can show upcoming, past, or both.
            if (method_exists($model, 'scopeFuture')) {
                $attributes[] = Attribute::make('period')->select()->default('upcoming')
                    ->label(['nl' => 'Periode', 'en' => 'Period'])
                    // Option labels are echoed as-is by leap's select — unlike label() and
                    // hint(), a per-locale array is not resolved there and lands in
                    // htmlspecialchars() as an array. So these go through __().
                    ->values([
                        'upcoming' => __('Upcoming'),
                        'both' => __('Including past'),
                        'past' => __('Past only'),
                    ]);
            }

            // The tag filter, only when tags are installed.
            if (class_exists(Tag::class)) {
                $attributes[] = Attribute::make('tag')->select()
                    ->label(['nl' => 'Filter op tag', 'en' => 'Filter by tag'])
                    ->hint(['nl' => 'Leeg = alle. Kies een tag om deze rij ertoe te beperken.', 'en' => 'Empty = all. Pick a tag to narrow this row to it.'])
                    ->values(['' => '—'] + Tag::orderBy('sort')->pluck('name', 'id')->all());
            }

            $attributes[] = Attribute::make('layout')->select()
                ->label(['nl' => 'Weergave', 'en' => 'Layout'])
                ->hint(['nl' => 'Grid zet de kaarten onder elkaar door; een rij scrolt zijwaarts en laat een halve kaart uitsteken.', 'en' => 'A grid wraps the cards onto rows; a row scrolls sideways, with half a card showing.'])
                ->values([
                    'grid' => __('Grid'),
                    'horizontal' => __('Horizontal row'),
                ]);

            $attributes[] = Attribute::make('columns')->select()
                ->label(['nl' => 'Kaarten naast elkaar', 'en' => 'Cards side by side'])
                ->hint(['nl' => 'Leeg volgt de site. In een grid is dit het aantal kolommen, in een rij hoeveel er volledig in beeld staan. Op tablet worden het er hoogstens twee, op mobiel één.', 'en' => 'Empty follows the site. In a grid this is the column count, in a row how many fit fully in view. Tablets get at most two, phones one.'])
                ->values(['' => '—', 2 => '2', 3 => '3', 4 => '4']);

            $attributes[] = Attribute::make('limit')->label(['nl' => 'Aantal (leeg = alles)', 'en' => 'Limit (empty = all)'])
                ->hint(['nl' => 'Leeg toont alles én geeft de tag-filter; dit is de overzichtspagina. Een getal maakt er een teaser van, met een link naar het overzicht.', 'en' => 'Empty shows everything and adds the tag filter; this is the overview page. A number makes it a teaser, with a link to the overview.']);
            // A limited section links to its overview on its own, so these two are an
            // override and not something to fill in. The label comes first and the URL
            // only appears once it has text: on its own an address with nothing to click
            // is not a link, and the overview is where it would have gone anyway.
            $attributes[] = Attribute::make('link_label')
                ->label(['nl' => 'Link-tekst (leeg = automatisch)', 'en' => 'Link label (empty = automatic)'])
                ->hint(['nl' => 'Een beperkte sectie linkt vanzelf naar het overzicht. Vul dit alleen als je een andere tekst wilt.', 'en' => 'A limited section links to its overview by itself. Fill this in only to word it differently.'])
                ->translatable();

            $attributes[] = Attribute::make('link')
                ->label(['nl' => 'Link-adres', 'en' => 'Link URL'])
                ->hint(['nl' => 'Leeg wijst naar de overzichtspagina van dit type.', 'en' => 'Empty points at this type\'s overview page.'])
                ->translatable()
                ->showIf('link_label');

            $sections[] = Section::make($key)->view($view)
                ->label(['nl' => 'Kaartrij: '.Str::headline($key), 'en' => 'Card row: '.Str::headline($key)])
                ->attributes(...$attributes);
        }

        return $sections;
    }

    public $icon = 'fas-sitemap';

    public $priority = -2;

    public $orderBy = 'sort';

    public $active = 'active';

    public $title = [
        'nl' => 'Website pagina\'s',
        'en' => 'Website pages',
    ];
}
