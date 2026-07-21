<?php

namespace Database\Seeders;

use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use NickDeKruijk\Settings\Setting;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Titles, slugs, descriptions and translatable section fields (head/body/
     * button) are seeded per locale (nl/en). When leap.locales is null the extra
     * locales are simply never shown.
     *
     * Note: the homepage uses the reserved slug "/" (not "home"), so it resolves
     * order-independently and is not also reachable under a second URL.
     */
    /**
     * Every language leap:template can install. A translation set is told apart from an
     * ordinary array by having only these as keys.
     */
    protected const LANGUAGES = ['nl', 'en', 'de', 'fr', 'es', 'it', 'pt', 'pl'];

    /**
     * Seed a page, with its sample text stripped to the languages this site has.
     */
    protected function page(int $id, array $data): void
    {
        Page::updateOrCreate(['id' => $id], $this->forSite($data));
    }

    /**
     * Strip sample content down to the languages this site actually has.
     *
     * The seeds carry every language so any install has something to show, but writing all
     * of them would leave rows carrying text in languages nobody picked: invisible on the
     * site, unreachable from the admin's language switcher, and there forever. A locale the
     * seeds have no text for falls back to English rather than seeding a blank.
     *
     * Recursive, because the translation sets are nested inside the sections.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function forSite(array $data): array
    {
        $locales = config('leap.locales')
            ? array_keys(config('leap.locales'))
            : [app()->getLocale()];

        if ($this->isTranslationSet($data)) {
            $kept = [];
            foreach ($locales as $locale) {
                $kept[$locale] = $data[$locale] ?? $data['en'] ?? reset($data);
            }

            return $kept;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->forSite($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    protected function isTranslationSet(array $data): bool
    {
        return $data !== []
            && ! array_is_list($data)
            && array_diff(array_keys($data), self::LANGUAGES) === [];
    }

    public function run(): void
    {
        $this->page(1, [
            'title' => ['nl' => 'Home', 'en' => 'Home'],
            'slug' => ['nl' => '/', 'en' => '/'],
            'menuitem' => false,
            'sort' => 1,
            'description' => [
                'nl' => 'Welkom op de voorbeeldwebsite gebouwd met de leap-template.',
                'en' => 'Welcome to the example website built with the leap template.',
            ],
            'sections' => [
                [
                    '_name' => 'slide',
                    '_sort' => 0,
                    'active' => true,
                    // The slides ship without an image and fall back to a dark gradient, so
                    // they say white rather than leaning on the placeholder's own default.
                    'white_text' => true,
                    'head' => ['nl' => 'Welkom', 'en' => 'Welcome'],
                    'body' => ['nl' => '<p>Een frisse start voor je nieuwe website.</p>', 'en' => '<p>A fresh start for your new website.</p>'],
                ],
                [
                    '_name' => 'slide',
                    '_sort' => 1,
                    'active' => true,
                    'white_text' => true,
                    'head' => ['nl' => 'Volledig zelf te beheren', 'en' => 'Fully self-manageable'],
                    'body' => ['nl' => '<p>Beheer pagina\'s en secties in het adminpaneel.</p>', 'en' => '<p>Manage pages and sections in the admin panel.</p>'],
                ],
                [
                    '_name' => 'slide',
                    '_sort' => 2,
                    'active' => true,
                    'white_text' => true,
                    'head' => ['nl' => 'Toegankelijk & snel', 'en' => 'Accessible & fast'],
                    'body' => ['nl' => '<p>Semantische HTML, responsive en zonder zware buildstap.</p>', 'en' => '<p>Semantic HTML, responsive and without a heavy build step.</p>'],
                ],
                // Every "default" (text-with-image) variant, one per image_position,
                // so the homepage shows all layouts at once. Add real images per
                // section in the admin panel; without one the text spans full width.
                [
                    '_name' => 'default',
                    '_sort' => 3,
                    'active' => true,
                    'head' => ['nl' => 'Afbeelding links', 'en' => 'Image left'],
                    'image_position' => 'left',
                    'body' => ['nl' => '<p>Tekst met een vierkante afbeelding links ernaast.</p>', 'en' => '<p>Text with a square image to its left.</p>'],
                ],
                [
                    '_name' => 'default',
                    '_sort' => 4,
                    'active' => true,
                    'head' => ['nl' => 'Afbeelding rechts', 'en' => 'Image right'],
                    'image_position' => 'right',
                    'body' => ['nl' => '<p>Tekst met een vierkante afbeelding rechts ernaast.</p>', 'en' => '<p>Text with a square image to its right.</p>'],
                ],
                [
                    '_name' => 'default',
                    '_sort' => 5,
                    'active' => true,
                    'head' => ['nl' => 'Breedbeeld', 'en' => 'Wide'],
                    'image_position' => 'bottom wide',
                    'body' => ['nl' => '<p>Een breedbeeld-afbeelding onder de tekst, over de volle breedte.</p>', 'en' => '<p>A full-width wide image below the text.</p>'],
                ],
                [
                    '_name' => 'quote',
                    '_view' => 'sections.default',
                    '_sort' => 6,
                    'active' => true,
                    'head' => ['nl' => 'Een goede website verkoopt zichzelf.', 'en' => 'A good website sells itself.'],
                    'body' => ['nl' => 'Een tevreden klant', 'en' => 'A happy customer'],
                ],
                // Content-type teaser rows (news/events/…) are appended here by
                // seedContent() after the model seeders have created the items.
                [
                    '_name' => 'cta',
                    '_view' => 'sections.default',
                    '_sort' => 13,
                    'active' => true,
                    'head' => ['nl' => 'Klaar om te beginnen?', 'en' => 'Ready to get started?'],
                    'body' => ['nl' => '<p><a class="button" href="/contact">Neem contact op</a></p>', 'en' => '<p><a class="button" href="/en/contact">Get in touch</a></p>'],
                ],
            ],
        ]);

        $this->page(2, [
            'title' => ['nl' => 'Over ons', 'en' => 'About us'],
            'slug' => ['nl' => 'over-ons', 'en' => 'about-us'],
            // High sort so About/Contact trail the content-type overviews (sort 100,
            // appended by each type's seeder) at the end of the menu, regardless of the
            // order pages are seeded in.
            'sort' => 200,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Over ons', 'en' => 'About us'],
                    'image_position' => 'left',
                    'body' => ['nl' => '<p>Vertel hier het verhaal achter je organisatie.</p>', 'en' => '<p>Tell the story behind your organisation here.</p>'],
                ],
            ],
        ]);

        $this->page(3, [
            'title' => ['nl' => 'Diensten', 'en' => 'Services'],
            'parent' => 2,
            'slug' => ['nl' => 'diensten', 'en' => 'services'],
            'sort' => 1,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Wat we doen', 'en' => 'What we do'],
                    'body' => ['nl' => '<p>Een voorbeeld van een subpagina onder "Over ons".</p>', 'en' => '<p>An example of a subpage under "About us".</p>'],
                ],
            ],
        ]);

        $this->page(4, [
            'title' => ['nl' => 'Contact', 'en' => 'Contact'],
            'slug' => ['nl' => 'contact', 'en' => 'contact'],
            'sort' => 210,
            'sections' => [
                [
                    '_name' => 'default',
                    '_sort' => 0,
                    'active' => true,
                    'head' => ['nl' => 'Contact', 'en' => 'Contact'],
                    'body' => ['nl' => '<p>Zet hier je contactgegevens of een formulier.</p>', 'en' => '<p>Put your contact details or a form here.</p>'],
                ],
            ],
        ]);

        // Legal pages, linked from the footer but hidden from the main navigation
        $this->page(5, [
            'title' => ['nl' => 'Privacybeleid', 'en' => 'Privacy policy'],
            'slug' => ['nl' => 'privacy', 'en' => 'privacy'],
            'menuitem' => false,
            'sort' => 4,
            'sections' => [
                ['_name' => 'default', '_sort' => 0, 'active' => true, 'head' => ['nl' => 'Privacybeleid', 'en' => 'Privacy policy'], 'body' => ['nl' => '<p>Beschrijf hier hoe je met persoonsgegevens omgaat.</p>', 'en' => '<p>Describe how you handle personal data here.</p>']],
                // The cookie table renders config('leap.consent'), so the privacy page
                // cannot drift away from the cookies the site actually sets.
                ['_name' => 'cookies', '_sort' => 1, 'active' => true, 'head' => ['nl' => 'Cookies', 'en' => 'Cookies'], 'body' => ['nl' => '<p>Hieronder staat welke cookies deze website gebruikt, waarvoor, en hoe lang ze bewaard worden.</p>', 'en' => '<p>Below is every cookie this website uses, what for, and how long it is kept.</p>']],
            ],
        ]);

        $this->page(6, [
            'title' => ['nl' => 'Algemene voorwaarden', 'en' => 'Terms & conditions'],
            'slug' => ['nl' => 'algemene-voorwaarden', 'en' => 'terms'],
            'menuitem' => false,
            'sort' => 5,
            'sections' => [
                ['_name' => 'default', '_sort' => 0, 'active' => true, 'head' => ['nl' => 'Algemene voorwaarden', 'en' => 'Terms & conditions'], 'body' => ['nl' => '<p>Zet hier je algemene voorwaarden.</p>', 'en' => '<p>Put your terms and conditions here.</p>']],
            ],
        ]);

        // Content types: tags, each type's own seeder (overview page + items), and a
        // home teaser row per type.
        $this->seedContent();

        // Default footer settings (only when the settings package is installed).
        // socials and footer_links use "label:url" per line (the ':' key separator of setting_array()).
        if (class_exists(Setting::class)) {
            Setting::set([
                'html_head' => [
                    'value' => '',
                    'description' => 'Code direct vóór </head>. Alleen voor code die GÉÉN toestemming vereist — trackers horen in de scripts_-instellingen, anders draaien ze buiten het cookiesysteem om.',
                ],
                'scripts_analytics' => [
                    'value' => '',
                    'description' => 'Statistiek-scripts (bijv. Google Analytics): plak hier de code van de leverancier. Draait pas nadat de bezoeker statistieken heeft toegestaan.',
                ],
                'scripts_embeds' => [
                    'value' => '',
                    'description' => 'Scripts voor ingesloten inhoud. Draait pas nadat de bezoeker die inhoud toestaat.',
                ],
                'footer_contact' => [
                    'value' => "Voorbeeldstraat 1\n1234 AB Amsterdam\ninfo@example.com",
                    'description' => 'Adres/contactgegevens in de footer',
                ],
                'socials' => [
                    'value' => "instagram:https://instagram.com\nlinkedin:https://linkedin.com\nfacebook:https://facebook.com",
                    'description' => 'Social media, één "naam:url" per regel (naam = FontAwesome brand-icoon)',
                ],
                'footer_copyright' => [
                    'value' => '© '.date('Y').' '.config('app.name'),
                    'description' => 'Copyright-regel onderaan de footer',
                ],
                'footer_links' => [
                    'value' => "Privacy:/privacy\nAlgemene voorwaarden:/algemene-voorwaarden",
                    'description' => 'Footer-links, één "label:url" per regel',
                ],
                'og_image' => [
                    'value' => '',
                    'description' => 'Standaard social-share afbeelding (URL of /storage-pad); pagina-eigen afbeelding gaat voor',
                ],
            ]);
        }
    }

    /**
     * Seed the registered content types: a handful of shared tags (so the model
     * seeders can attach them), each type's own seeder (its overview page + sample
     * items), and a home teaser row per type linking into it. PageSeeder is the only
     * writer of the home page's sections, so appending teasers here never clobbers.
     */
    protected function seedContent(): void
    {
        $models = PageController::indexModels();
        if (empty($models)) {
            return;
        }

        // Tags first, so a type's seeder can attach them to its items. Their name is
        // translatable, so it is seeded per locale — a plain string lands in whichever
        // locale happens to be active while seeding and leaves the filter chips above
        // every other overview reading that one language.
        //
        // Every language leap:template can install is here. Storing a locale the site did
        // not pick costs a few bytes and shows nowhere, which is cheaper than a tag that
        // only speaks Dutch.
        if (class_exists(Tag::class) && Tag::count() === 0) {
            foreach ([
                ['nl' => 'Algemeen', 'en' => 'General', 'de' => 'Allgemein', 'fr' => 'Général', 'es' => 'General', 'it' => 'Generale', 'pt' => 'Geral', 'pl' => 'Ogólne'],
                ['nl' => 'Update', 'en' => 'Update', 'de' => 'Update', 'fr' => 'Mise à jour', 'es' => 'Actualización', 'it' => 'Aggiornamento', 'pt' => 'Atualização', 'pl' => 'Aktualizacja'],
                ['nl' => 'Achtergrond', 'en' => 'Background', 'de' => 'Hintergrund', 'fr' => 'Contexte', 'es' => 'Contexto', 'it' => 'Approfondimento', 'pt' => 'Contexto', 'pl' => 'Tło'],
                ['nl' => 'Aankondiging', 'en' => 'Announcement', 'de' => 'Ankündigung', 'fr' => 'Annonce', 'es' => 'Anuncio', 'it' => 'Annuncio', 'pt' => 'Anúncio', 'pl' => 'Ogłoszenie'],
            ] as $sort => $name) {
                Tag::create($this->forSite(['name' => $name]) + ['sort' => $sort + 1]);
            }
        }

        foreach ($models as $model) {
            $seeder = 'Database\\Seeders\\'.class_basename($model).'Seeder';
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }

        // A teaser row per type on the home page (its overview is in the nav already).
        $home = Page::find(1);
        $sections = $home->sections;
        $sort = 7;
        $position = 0;

        foreach ($models as $key => $model) {
            $overview = PageController::overviewPage($key);
            if ($overview) {
                // Realign the overview's menu position (sort) to the registry order, so a
                // re-seed after a reordered leap.content moves the menu too — the page keeps
                // whatever order it was first created with otherwise.
                $overview->update(['sort' => 100 + $position]);

                $sections[] = [
                    '_name' => $key,
                    '_view' => 'sections.items',
                    '_sort' => $sort++,
                    'active' => true,
                    'head' => $overview->title,
                    'limit' => 6,
                ];
            }
            $position++;
        }

        $home->update(['sections' => $sections]);
    }
}
