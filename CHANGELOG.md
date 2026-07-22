# Changelog

All notable changes to `nickdekruijk/leap-template` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.10.16] — 2026-07-22

### Added

- **Search results are ordered by how well they match.** They came back in the order the sources
  happen to be read in — every page first, then each content type — so a card actually called
  "Consent" sat below three pages that mention the word once in a section body, and ten page hits
  pushed every content item off the list entirely. Each result is now scored: an exact title beats
  a title that starts with the term, which beats a title that contains it, which beats a match in
  an intro or description, which beats a match somewhere in a section. Ties keep the order they
  arrived in, so equally good results still read in each source's own order — pages by their menu
  position, releases by date.

### Fixed

- **A page in the search results now links into the language you are reading.** Item results have
  always been built by `PageController::itemUrl()`, which carries the locale prefix; a page result
  was built from its slugs alone, so every page hit on `/nl` sent the visitor to the default
  language's copy of that page. It gets the same prefix now. The path cache behind it is keyed by
  locale as well — slugs differ per language, and a cache that forgets that hands one language's
  paths to another.

- **The search overlay scrolls once the results outgrow it.** The panel had no height limit and
  nothing to scroll, so with a screenful of hits the last ones sat below the fold with no way to
  reach them. It is a column now, capped at the viewport minus the offset it opens at, with only
  the result list scrolling — the field you are typing in stays where it is. `overscroll-behavior`
  keeps a flick at the end of the list from scrolling the page behind it.

- **The logo links to the homepage of the language you are reading.** It pointed at `/` from every
  page, so one click on a prefixed page dropped the visitor into the default language. The 404
  page's way back had the same hard-coded root, and its label was hard-coded Dutch on every site,
  whatever language it was installed in — it is a translation key now, shipped in all seven
  languages the template carries.

- **Search no longer memoises page paths for the life of the process.** The path cache was a
  `static` inside the method, which outlives the request in a long-running worker: after a slug was
  edited, results kept pointing at the old address until the worker was replaced. It is an instance
  property now — one request, one memo — and so is the column-exists cache beside it.

## [0.10.15] — 2026-07-22

### Added

- **A first-time visitor on `/` is offered the language their browser asks for.** A multilingual
  site served its default language to everyone who typed the bare domain, however loudly their
  browser said otherwise — a Dutch visitor landed on the English homepage and had to find the
  switcher. `PageController::route()` now reads `Accept-Language` on the root and redirects to
  `Leap::localePrefix($preferred)` when it names one of the site's own locales other than the
  default.

  Everything about it is deliberately narrow. **Only the bare root**: every other URL already
  says which language it is in, in its prefix. **Only once**: each frontend request records the
  locale being read in the session, so the redirect fires before the first page and never again
  — a visitor who then picks the other language by hand keeps it. **Only with a header**: a
  crawler sends none and keeps seeing the default locale on the unprefixed URLs that the sitemap
  and the hreflang alternates point it at. And **only a 302**, because a language preference is
  not a move: the same URL answers differently for the next visitor.

  The root's response carries `Vary: Accept-Language`, without which a proxy or CDN would hand
  one visitor's language to everyone behind it.

  A language the site does not speak changes nothing: `getPreferredLanguage()` falls back to the
  first of the list, which is the default locale, so an unmatched browser stays put.

  Note for existing projects: a test that asserts the root renders the default language while the
  test client asks for another one now meets the redirect. The template's own `MultilingualTest`
  sends `Accept-Language: nl` on the one request where that mattered — Symfony's test client
  always sends a header of its own, asking for English.

## [0.10.14] — 2026-07-22

### Added

- **Live demo site.** [leap.nickdekruijk.nl](https://leap.nickdekruijk.nl) is a stock
  `leap:template` install showing what the stubs produce; log in on
  [/admin](https://leap.nickdekruijk.nl/admin) with `info@example.com` / `leapdemo`.
  It resets to its seeded state 15 minutes after the last change.

### Fixed

- **A slide with an image no longer swallows its own text.** The image sat in the flow at
  `height: 100%`, so the container holding the heading and body was laid out underneath it and
  the slider's `overflow: hidden` cut it off. Only slides without an image — the placeholders
  every seeded site starts with — showed their text, which is why this survived until the first
  real photo was uploaded. The image is taken out of the flow and the content sits above it.

- **White slide text is legible over a photo.** The white default was written for the placeholder
  gradient, which is dark by construction; a photograph is not, and the text washed out over its
  lighter areas. A slide carrying an image and white text now gets a gradient scrim behind the
  content. Slides with dark text and the placeholders are untouched.

## [0.10.13] — 2026-07-21

### Added

- **`leap:template --diff` now reports the whole install, not only the files.** It compared a
  sha1 per template file and said nothing else — while a release that adds a database column or
  a translation key touches no file this project has. Those arrive silently and show up as an
  admin that refuses to save (this release's own `pages.breadcrumb`) or a Dutch page in English.
  On top of the file diff it now reports:

  - **columns a later release added**, read from the stub migrations themselves rather than a
    list that has to be kept up, with the declaration and a `make:migration` line to copy out.
    A table that is not there is skipped: no database is an unanswered question, not drift.
  - **translation keys the template added and this project never got.** A `lang/<code>.json` is
    no longer diffed at all: it is not a copy of the stub — the site has its own strings and
    `lang:add` merged Laravel's whole vocabulary into it — so only the one direction is drift.
    In leap-test that turned 290 lines of the project's own translations into one line, and the
    key the diff did flag turned out to be present all along, in a different position.
  - **the install steps that leave no trace in a file**: the catch-all and sitemap routes, the
    `public/storage` link, the `.gitignore` rules, `leap.tinymce.content_css`, `PageSeeder` and
    `WithoutModelEvents` in `DatabaseSeeder`, the Leap traits on `User`, the frontend packages
    and Laravel's own translations per locale. Each is one line with a ✓, or a ✗ with the
    command that fixes it.

  It still writes nothing and still exits 0 — it is a report, and it has to be safe to run in a
  project that is customised on purpose.

- **The install now offers Laravel's own translations for the languages you picked.** It already
  copied `lang/nl.json` — the template's own strings — but the framework's, the validation errors
  and the password mails, stayed English, so a fresh Dutch site rejected a form in a language it
  does not speak. `leap:template` now runs `composer require --dev laravel-lang/common` and
  `php artisan lang:add <codes>` for every chosen non-English locale that has no `lang/<code>/`
  yet. Laravel ships no translations of its own — `lang:publish` only writes `lang/en` — and the
  package is a dev dependency because it publishes files into the repository and nothing at
  runtime depends on it afterwards. `lang:add` merges into an existing `lang/<code>.json`, so the
  template's strings survive. An English-only site is not asked and `--no-install` prints the
  commands instead of reaching Packagist.

  A re-run does not ask again, but it does merge those keys back: copying the template's
  `lang/<code>.json` over your copy puts the bare stub back, and the framework's strings would be
  gone with it. Accepting that overwrite already answered the question, so the merge happens
  without a second one.

- **A breadcrumb on every page but the homepage.** A detail page carried a lone link to the
  overview it hangs under, and an ordinary page had nothing at all: a visitor on
  `/over-ons/diensten` was told neither where they were nor how to step back up. The trail —
  the homepage as a house icon, every ancestor, then the current page — comes from
  `PageController::breadcrumbs()` and is rendered by `components/breadcrumbs.blade.php`, which
  the layout drops above the content. It shows itself whenever the trail is longer than a single
  step, so the homepage keeps quiet without a rule of its own.

  The page you are on is named but never linked, since it is a position rather than a
  destination, and neither is an ancestor whose slug is untranslated in the active locale — the
  same pages the router already refuses to serve there. In front of the trail sits a **back
  link** to the page above: a plain `<a>`, so it survives a visitor arriving from a search
  result and a crawler follows it, upgraded by Alpine to the browser's own back when the
  referrer *is* that page — which returns a filtered, scrolled overview exactly as they left it,
  for no request.

  Editors get a **"Toon kruimelpad" switch per page** (new `breadcrumb` column, on by default);
  a content item follows the overview page it lives under. Existing sites need the column added
  by hand — the migration stub only runs on a fresh install — and `leap:template --diff` now
  names it, with the migration line to copy out.

- **The `BreadcrumbList` structured data now describes the whole trail**, on ordinary pages too.
  It used to be written by the item schema partial, listing two steps and skipping the homepage,
  and it was the only place a page's position was recorded. It is emitted from the same trail the
  visitor sees, so the two cannot drift apart.

- **A starter test for what the section views render**, covering the above: the carousel opens
  and closes exactly once whichever slide is switched off, the white text follows its switch, and
  a text section saved under `dark_background` still comes out white.

- **The tags on a detail page are links now.** They looked exactly like the filter chips above
  the overview — same shape, same colour — and did nothing when clicked. A visitor reading a
  project tagged "hout" had to go back up and find that chip themselves. Each tag now points at
  its type's overview with `?tag=` already set, the same URL the filter chips use, so the two can
  never disagree on the slug. A type without an overview page has nowhere to send them and keeps
  its chips as plain text.

### Fixed

- **The breadcrumb starter test is installed.** It was listed among the template's files but not
  among the tests the installer copies, so a fresh project never got it — and `--diff` reported
  it as missing from then on.

- **The `BreadcrumbList` no longer writes a step a crawler cannot follow.** An ancestor with no
  slug in the active locale — a page that only groups its children — has no URL, and it was
  written into the structured data without one. Every step but the last needs an `item`, so such
  an ancestor is left out of the JSON-LD and the steps behind it close up. The visible trail
  still names it: a visitor is helped by the name, a crawler only by a list it can follow.

- **A title holding `</script>` can no longer break out of the JSON-LD block.** Both structured
  data blocks encode with `JSON_HEX_TAG` now. Editor input, so nothing was open to the outside,
  but it took one flag.

- **A detail page starts at the same height as the overview it came from.** Every section opens
  at `--space-xl`, but an item's header opened at `--space-l`, so stepping from an events
  overview into an event pulled the title up by a step. Nobody could see it while the two pages
  began with different things; now that both start with a breadcrumb, the gap under it jumped.

- **"Witte tekst" on a slide does something in both positions.** The view asked `@isset()`, but a
  switch that is off stores `false` rather than dropping the key — so every slide was white and
  the option was decoration. And a slide with no image draws a dark gradient and is styled white
  for contrast, which an *absent* class cannot override: switching the option off has to say so.
  The slide content now carries `white` or `dark-text`, and the placeholder rule stands down for
  `dark-text`. A slide saved before the option existed has neither and keeps the old default.

- **Deactivating a slide no longer lets the next section slide over the carousel.** The cause is
  in leap 0.10.15, which this release requires. The `->where('active', true)` in `page.blade.php`
  and `item.blade.php` is gone with it — `sections()` filters now, and doing it in the template
  was what broke the carousel.

- **An overview page has an h1 again.** A news or events overview is a page with one card row on
  it, and that row's heading was hard-coded to `h2` — so the page had no h1 at all, and the
  heading rendered at body size on top of it, since `.article` (which restores heading sizes
  after the reset) sits on the cards rather than on the row's header.

  Which section carries the h1 is decided by the page now, in
  `PageController::headingSectionIndex()`, and handed to each section as `headLevel`. It is the
  first section that renders a heading *and* has it filled in — a rule the text section could not
  apply on its own, because a section cannot see whether an earlier one already held a heading.
  A quote and a video are skipped: neither renders a heading, and the h1 used to land on them and
  disappear. `item.blade.php` hands out `h2` throughout, its header having the h1 already, which
  is what the `hasHeading` flag used to say.

- **A carousel no longer carries the h1.** It swaps its content every few seconds, so the heading
  a visitor happens to land on — or that a screen reader reaches — is not the one the page is
  about. Slide headings are `<p class="head">`, sized as before. A single slide is a static hero
  rather than a carousel and does take the h1, which leans on leap 0.10.15 dropping inactive
  sections before a run is marked: switch one of two slides off and the one left over is a hero.
  A slide on an item detail page used to render a second h1 next to the item's title; it no
  longer can.

- **An empty heading renders no tag.** The text section wrote its tag unconditionally, so a
  section with no heading left a bare `<h1></h1>` on the page — worse for search engines and
  screen readers than no heading, and it took the h1 away from the section that did have one.

### Changed

- **A background photo is shown as it was uploaded.** A text section with white text laid a 45%
  black wash over its background photo, so every photo behind one came out muddier than the
  editor picked it — and there was no way to say no. Choosing a photo that carries white text is
  their call. The wash is gone, and with it `.section-overlay`. A section with no photo still
  gets the gradient, so the text has something to sit on either way.

- **"Witte tekst" now means the same thing in every section, and says the rest in a hint.** A
  slide read "Witte tekst (voor op donkere achtergrond)" and a text section "Donkere achtergrond
  (witte tekst)" — near-mirrors, each naming what the other one's label left out. Neither was
  right for long: "donkere achtergrond" is not what happens when the section already has a
  background photo, where the switch only lays a wash over it.

  Both are "Witte tekst" now, because that is the thing an editor is after either way — the
  backdrop is dark and the text has to hold up against it. How each section gets there differs,
  and that is what the hint under the switch is for. It has room for all of it, which the label
  never had: a text section with white text also turns its links white, and stands a gradient in
  when there is no photo behind it.

  The field and its CSS class followed the label. `dark_background` is `white_text`, the same
  name the slide already used, and the class on the section is `white-text` rather than `dark`
  — on a slide, `white` as well. A page saved under the old key still renders white:
  `sections/default.blade.php` reads `white_text` and falls back to `dark_background`, and the
  editor writes the new name from the next save. A project that styled `.dark` in its own
  `project.scss` has to rename that rule.

- **A new slide starts with white text on.** It was off, which read as a considered default and
  was not one: `Attribute::default()` is only written when it is truthy, so the option was simply
  absent on a new slide and the answer came from the placeholder styling instead. Touch the
  switch twice and the same slide turned dark, because now the `false` was really there. A slide
  is a photo with text over it and is nearly always dark enough to want white, so that is what it
  starts as — and it is the switch that decides either way, not the history of the section.

- **The seeded slides say white text.** They ship without an image, so they draw the dark
  gradient and were only white through the placeholder's own default — which the option can now
  override. Saying it outright keeps them readable, and keeps them so once a real image is put
  behind them.

- **An overview honours `?tag=` before Alpine boots.** The filter was client-side only, which was
  fine while the parameter could only be set by a click. Now that a link arrives on a filtered URL,
  the whole grid would paint first and the non-matching cards blink out a moment later. The server
  states the same answer up front: the chip rendered active, the other cards with `display: none`,
  which is the very style `x-show` writes — so Alpine takes the hiding over on boot and "All" still
  brings everything back. A `?tag=` no chip offers is ignored rather than emptying the grid.

## [0.10.12] — 2026-07-20

### Added

- **Requires leap 0.10.14.** The "view all" URL field is hidden until its label has text, through
  `showIf()` — named `showWhenTrue()` before 0.10.14, and unable to read a translatable trigger
  before it.

- **A card row chooses its own layout, and how many cards stand side by side.** The limit decided
  everything: empty meant every item in a grid, a number meant a sideways-scrolling teaser. So a
  row of six in three columns — a teaser that reads as part of the page rather than a filmstrip —
  could not be asked for at all. One setting was doing two jobs.

  Layout and column count are their own fields now. A section saved before they existed has
  neither and falls back to the old rule, so nothing rearranges itself on upgrade. The count
  applies to both layouts and means the same in each: how many cards stand beside one another. All
  three screen sizes are written into the section's `style`, because `grid-template-columns` takes
  a track count and `repeat()` will not evaluate a `calc()` — CSS cannot narrow the number down for
  a tablet by itself. A tablet gets at most two, a phone one.

  A horizontal row does its own arithmetic, which `calc()` does allow, so its cards are as wide as
  the count asks for plus half of the next one. That half card is the only thing that says the row
  continues; the width used to be a flat `min(80vw, 360px)` that happened to leave a sliver at the
  usual content width and nothing at others.

  A teaser also links to the overview it previews, falling back to the page that lists its type.
  That URL was there all along — `items()` builds it to give every card its detail link — but it
  had to be typed into two fields by hand, so in practice it was left empty. `PageController` now
  exposes it as `overviewUrl()` and uses it for both.

- **The install ends with a user that can open the admin panel.** Leap's `role_user` migration
  seeds the superuser role and attaches the first existing user to it — but a fresh install has
  no user yet, and the installer only seeds `PageSeeder`, so nothing ever claimed that role.
  Whoever ran `db:seed` afterwards got Laravel's `test@example.com` without one, which
  `RequireRole` answers with a 403: an install that finished successfully and still had no way in.

  A last question now creates the user (defaulting to that same address, so an already seeded one
  is reused rather than duplicated) and hands it the role, via `leap:user --role` — which is why
  this needs leap 0.10.13. Under `--fresh` it runs unattended and prints the generated password
  once. Decline it, or migrate nothing, and the closing summary still says how to do it by hand.

- **`leap:content-delete` takes a generated content type back.** `leap:content` wrote five files,
  a registry entry, a table and the page that lists it, and nothing undid any of that. A typo in
  `leap:template --models=…` therefore cost an afternoon of hand-editing across six places, and
  missing one of them left `leap.content` pointing at a class that no longer existed.

  Files and the registry entry go. The table, its migration record, its tag links and its overview
  page are a separate question that defaults to no, with the row count in it, so a reflex Enter
  keeps the data; `--drop-table` answers it up front and a run without a tty never drops anything.

  The name is checked against what owns the registry key before anything is touched.
  `Str::plural('Events')` is `Events`, so deleting a stray type generated from a plural name
  derives to the real `Event`'s table, page and registration — without that check the command
  would be more dangerous than the hand-editing it replaces. It removes the stray files and leaves
  the rest alone, saying so.

- **The accordion opens and shuts as one movement.** 0.10.11 styled it and left the panel to the
  browser: the chevron turned over 0.2s while the panel snapped, which read as two events rather
  than one. Chrome animates to the real height through `interpolate-size`; Safari and Firefox have
  `::details-content` but not `interpolate-size`, and there is nothing to load that would give it
  to them, so `scripts.js` measures each panel and hands the height to CSS as `--panel-height`.

  Closing is taken over in JavaScript as well. A `<details>` drops its content the moment the
  `open` attribute goes, so the panel is gone before a transition can run — `content-visibility`
  with `allow-discrete` is supposed to hold it there and Safari does not honour it. The element is
  kept open until the panel has travelled, and a `.closing` class turns the chevron back on the
  click rather than at the end. Where the CSS route works, none of this code runs at all.

- **A content item's tags are chips on its detail page.** The filter above an overview and the tags
  on the item itself are the same thing, but only the filter looked like it: the detail page put a
  "Tags" label beside the names glued together with commas. The chip is described once now and both
  places use it — the filter on its `<a>`, which Alpine intercepts, and the detail page on its
  `<li>`, which is only a label. The filter chips gained the rounded corners they lacked.

- **A news-shaped item is dated by its publication.** An event has a `date` column and always
  showed one; a news item has only `published_at`, so its cards and its detail page showed no date
  at all. Being published is what dates such an item, so a `date` accessor hands that to the shared
  item views. It sits on the model rather than in the views on purpose: a type with no meaningful
  date then has none, instead of every type inheriting a fallback it did not ask for.

  Dates now open with the weekday — written out on a detail page, abbreviated on a card where the
  line has to stay narrow. Dutch abbreviates with a full stop, which reads as a typo beside the
  number, so that is dropped.

- **Tags are filterable in the admin list.** The field exists so that content can be found by them,
  but in the overview it was only a column: no index, no filter. All three content archetypes ship
  the same field and now get the same treatment.

### Changed

- **Seeded events have varied times, and two deliberate edge cases.** Every seeded event ran
  20:00–22:00 on a date picked at random between a month back and three months on. That said
  nothing about how a card handles the width of a time, never produced either case the model has a
  rule for, and — because an overview shows upcoming events by default — made a set of six turn up
  as anywhere between two and six, differently on every reseed.

  Times land on the half hour and run one to four hours. The seeder asks for six upcoming and six
  finished, so a section set to past or both has as much to show as the default one, and two of the
  upcoming ones are deliberate: one running past midnight, which ends the next day, and one with no
  end time, which lasts until the end of its own day. Both are dated `future()` on purpose — being
  created is not the same as being seen, and a random date could hide the very thing they exist to
  demonstrate.

### Fixed

- **A select option is translated with `__()`, not with a per-locale array.** Leap resolves such an
  array for `label()` and `hint()`, but the options of a select are printed as they are — so an
  array reaches `htmlspecialchars()` and takes the whole editor down with a TypeError, on any page
  carrying that section. The events `period` field had been written that way from the start and
  was waiting for someone to open a page that used it.

  A test now walks every section the page resource offers and holds each option to being a string,
  because the failure only shows up in the one place nobody looks first.

- **A slug no longer falls back to another language.** `loadPages()` read the page's slug as a
  plain attribute (`$page->only()`), and laravel-translatable answers that with
  `config('app.fallback_locale')` filled in when the active locale has no translation. Harmless
  while the fallback is a language the site does not serve — Laravel's default `en` on a Dutch-only
  site — and silently wrong the moment it is one of its own: a page with no English slug then
  answered on `/en/{its Dutch slug}` and appeared in the English menu, rendering Dutch.

  A slug is an address, not prose. `buildLocalePath()`, which builds the sitemap, has always asked
  without the fallback and left such a page out, so the two halves of the same site disagreed about
  which URLs exist — decided by one line in `.env`, per environment, in a file that is not in
  version control. The same shape as the `APP_LOCALE` leak leap fixed in 0.10.8.

- **A translatable section heading no longer breaks the navigation.** `loadPages()` collects the
  headings of sections flagged as a menu item and hands them to the menu as titles. Every heading
  in `ContentSections` is `translatable()`, so on a multilingual site that heading is a per-locale
  array — and this reads the raw `sections` cast rather than `HasSections`, so nothing resolved it.
  `Str::slug()` turned the array into the string `"array"`, giving every anchor the same `#array`,
  and the layout then rendered the title, where `htmlspecialchars()` refuses an array outright.

  The navigation lives in the layout, so that was a `TypeError` on every page of the site, caused
  by an editor ticking a switch the admin offers. Nothing in the shipped seed switches `menuitem`
  on, which is why a default install never met it. The heading is now resolved to the active
  locale, falling back to the first translation there is — the rule `HasSections` already uses —
  so a half-translated heading still reads.

- **`leap:template` copies `tests/Concerns/ResolvesContentPaths.php`.** `SeoTest` uses the trait
  and the installer's file list did not mention it, so a fresh install landed a test suite that
  fatally errored on the first file PHPUnit read: `Trait "Tests\Concerns\ResolvesContentPaths" not
  found`. Introduced with the trait itself and never released.

  `TemplateManifestTest` only ever checked that each listed file has a matching stub — never that
  each shipped stub is listed, which is the direction that hides a file rather than a typo. It now
  checks both, with the deliberate exceptions named: the tag filter and translations ship only when
  the project asked for them, and `routes/web.php` is patched in place rather than copied.

- **An overview page is named in each locale, not English everywhere.** The seeders mapped one
  English string over every locale, so a Dutch site got a page called "News" at `/news`. The title
  goes through the lang files per locale now and the slug is derived from it: with a translation
  present, a Dutch site gets Nieuws at `/nieuws`. A locale without one falls back to the English
  word rather than to nothing, so this is safe for a type nobody has translated yet.

  All three seeder archetypes carried the same shape and are fixed together. Every shipped language
  translates News, Events and Projects — without that, a German site would quietly serve News at
  `/news`, which is this bug one language over. The translation test could not see those names at
  all, since a seeder reaches `__()` through the `{{ Models }}` placeholder and scanning the stubs
  finds a placeholder, not a string; they are declared as source strings in their own right now.

- **A content type is not registered twice when the config imports its model.** `registerInConfig`
  matched only the fully qualified form, so a `config/leap.php` that imports its models and writes
  `'news' => News::class` looked unregistered and the key was appended again on every run. PHP
  resolves duplicate keys to the last one without complaining, so the file quietly grew a pair of
  lines each time the template was published. The key decides now, not how the class is spelled,
  and it is looked for inside the content array only — the doc comment above it carries an example
  of the same shape.

- **The sitemap route is registered.** `PageController` has had a `sitemap()` method all along, but
  `routes/web.php` never pointed at it: the catch-all swallowed `/sitemap.xml`, so the template
  shipped a sitemap nothing could reach — and publishing over a project that had added the route
  itself silently took it away again.

- **A card's body text is body colour, and only its heading takes the accent.** A card carries
  `.article` for its prose typography, and that rule colours every link in the accent. Here the
  link wraps the whole card, so the date and the intro were tinted along with the title. The card
  link inherits its colour now and the heading takes the accent explicitly rather than by accident.
  On a dark section the title stays white: an accent picked to read on the page background does not
  hold up over a photo or the placeholder gradient.

- **The heading above a card row is sized like any other section heading.** The reset drops every
  heading to body size and `.article` puts the sizes back — but on a card row that class sits on
  the cards, not on the header above them. The title of a news or events row therefore rendered
  bold and in the heading face at body size: close enough to look intentional, and wrong next to a
  default section.

- **A card's hover wash stays inside the card, and its focus ring is a normal focus ring.** The
  wash was an outline: drawn outside the box, so it reached past the card's edge and doubled as its
  padding. A horizontal row scrolls with `overflow-x`, which clips exactly that — and because the
  outline was as wide as the padding, the keyboard focus ring inherited that width and came out far
  too heavy.

  It is padding and a background now, so the wash stays inside the card and the global focus ring
  applies at its own weight, drawn just inside the edge so a scrolling row cannot cut it off. Both
  parts fade together: the outline was never in the transition, so it snapped into place around a
  background that was still arriving. Padding insets a card's content, so the row is pulled back out
  by the same amount and its gaps shrunk by it — the distance from content to content is what it
  was, and the heading above still lines up with the first card's title. The scrollbar under a
  horizontal row gets the same inset on its start side; the end side runs off screen anyway.

  The `tabindex` on a scrolling row is conditional now. A scroll region has to be reachable without
  a mouse, but linked cards are focusable themselves and tabbing already scrolls the row along, so
  the extra stop only earns its place when a card in the row has no detail page.

- **The bundled tests ask for a content path instead of hardcoding `/news`.** An overview page is
  named after the translated type, so it lives at `/nieuws` on a Dutch site and `/news` on an
  English one, and `SeoTest` wrote `/news` — which made it pass or fail on the language the project
  happens to be in. The helpers live in a trait rather than on `TestCase`: every Laravel app owns
  its own `TestCase`, and publishing one over it would take whatever the project had put there.

## [0.10.11] — 2026-07-16

### Added

- **The accordion is styled.** TinyMCE's accordion button ships in leap's default toolbar, so
  every editor can already reach for one — and what came out was the browser's own `<details>`:
  a native triangle, a summary at body size, no rule to separate one from the next. The template
  dressed everything else the editor can make and left this to the user agent.

  It is styled on the element, not on the `mce-accordion` class the plugin adds, so a `<details>`
  written by hand in the code view is indistinguishable from one clicked into place — the editor
  should not be able to tell where its accordion came from. The summary is set like the heading it
  is (heading font, `--fs-h4`, bold, matching `h1`–`h6`), the native marker is dropped in both its
  dialects, and the chevron is the one `.nav-submenu-caret` already uses rather than a second idea
  about what a chevron is. Rule and chevron take `--accent`; on a `.dark` section they turn white,
  for the same reason the link beside them already does — the accent is picked to sit on the page's
  own background, not on a photo darkened to 45% black.

  Consecutive accordions close ranks (no margin, no doubled rule) so a series reads as one list
  with a line between the rows instead of as scattered boxes. `public/css/tinymce.css` carries the
  same rules, so the editor and the page agree.

## [0.10.10] — 2026-07-16

### Changed

- **Requires `nickdekruijk/leap: ^0.10.10`** — the layout, the JSON-LD and the search excerpt
  all read `HasDocumentMeta::metaDescription()`, which lands in that release.

### Added

- **A seeded content item now carries a section.** The factories filled in a title and an intro
  and left `sections` null, so every seeded news item and event rendered a detail page that was
  nothing but its own header — the one page whose whole point is the sections. Each item gets a
  "text with image" section (image right, placeholder prose) to look at and to edit.
- **An item's intro is searchable.** It is marked `->searchable()` in the admin Resource, but the
  site search only ever read title, description and sections — leaving an item whose words live
  in its intro findable in the admin and nowhere else. The intro is a listed item's card text and
  often its only prose. Pages have no intro column, and the same query serves both, so the clause
  is gated on the schema.

### Fixed

- **The meta description no longer disagrees with the structured data.** `description` and `intro`
  are both optional: the layout read only `description` and the JSON-LD read only `intro`, so an
  item with just an intro shipped no meta/OG description at all, and an item with both described
  itself two different ways. Both now read `HasDocumentMeta::metaDescription()` — the description,
  else the intro. The search excerpt reads it too, instead of its own copy of the same idea.
- **An item's first section no longer emits a second `<h1>`.** The first section carries the page's
  `h1`, which is right for a page and wrong for an item, whose header already has one. `item.blade.php`
  now says so and the section steps down to `h2`. Only visible now that items seed a section.

## [0.10.9] — 2026-07-16

### Changed

- **The page tree is one question, not six.** `PageController`, the `Page` model, its
  migration, the `/admin` module, the `ContentSections` concern and the `Search` component
  were asked for one at a time, as if you could take some and leave the rest. You cannot: the
  controller routes what the model holds, the model needs its table, the module is built from
  the concern, and `layouts/app.blade.php` renders `<livewire:search />` whether or not the
  component is there — so answering no to the search component fataled every page. They are
  now installed as a set, under one question.

  Only while none of them are there. A file you already have is a different question: your
  copy is at stake, and you may have edited one and not the others, so a re-run asks about
  those one by one — the same thing `copyDir` already did for a changed view.

  What is genuinely optional stays its own question: the seeder (sample content), the
  TinyMCE stylesheet, and `HasTags` (which `--no-tags` already gates).

- **The starter tests are one question too**, and now include `SearchTest` and `SeoTest`.

- **The tag filter is one question, not six.** "Add the shared tag filter to content types?"
  was already the decision; the trait, the `Tag` model, its admin module, the factory and the
  two migrations it is made of were then asked for one by one. Worse, `HasTags` was asked in
  the main run — *before* the tag question — so `--no-tags` left a trait behind referring to
  an `App\Models\Tag` that was never created. The set now follows the decision that was
  already taken, silently. A file you edited yourself is still asked about on a re-run.

- **The "Plural of X?" question is gone**, from `leap:template` and `leap:content` both.

  It rested on a misunderstanding — that the plural becomes the overview URL. It does not.
  URLs come from the slug of the page whose section lists the type, per locale, which is the
  only way a Dutch site can be `/berichten` and its English twin `/news`. The plural is code:
  the table, the `leap.content` key, the section name. Nobody visiting the site ever sees it.

  Which leaves it a question with one right answer. Content types are named in English —
  they are classes — and `Str::plural` is English, so it is right by construction. `News`,
  `Event`, `Project`, `Story` all guess correctly; only a Dutch class name would not, and a
  Dutch class name buys nothing (see leap 0.10.9's docs). `--plural` and `Name:archetype`
  stay for the exceptions.

- **"Run database migrations now?" defaults to yes.** It was no, and nothing on record says
  why — the commit that added it only says it offers to run migrate. The installer has just
  written a migration whose only purpose is to run, and without it there is no `pages` table,
  so no site and no `/admin`: another no that breaks what was just installed. `--fresh`
  already answered yes here, so the unattended path and the interactive default disagreed
  about the right answer.

  The one real argument for no — `migrate` runs every pending migration, not just the
  template's — is now in the hint, where it can inform the answer instead of deciding it.
  Seeding still defaults to no: sample content is genuinely optional.

- **The questions are asked in the order the install happens.** Every `routes/web.php` edit
  is now together — the welcome route out, the sitemap and the catch-all in — instead of the
  route questions sitting after a `composer require`. And `composer require` is the last
  question of the run: it is the only step that leaves your machine, and it has nothing to
  say about your project.

  It cannot be the last *step*, though: `nickdekruijk/settings` ships a migration, so
  installing it after `php artisan migrate` leaves the settings table missing and the
  homepage at a 500 (fixed in 0.10.4 — this keeps it fixed). It runs immediately before the
  migrations, which is as late as it can go.

- **`/` is one question.** "Delete Laravel's welcome page?" and "Add PageController route?"
  were two, and only one of the four answers left a working site: drop the welcome route
  without the catch-all and `/` is a 404; add the catch-all without dropping it and the
  welcome page shadows the homepage. The catch-all's own hint said "without it the site has
  no pages at all", which is not a choice. Now: "Serve / from the page tree?", defaulting to
  yes, doing both. The sitemap stays its own question and is registered first, since the
  catch-all takes everything left.

- **Laravel's welcome page is one question, asked next to the other deletions.** The view and
  the route were two prompts, eighteen lines and a `composer require` apart, with opposite
  defaults — the view yes, the route no. Take both and
  `Route::get('/', fn () => view('welcome'))` was left pointing at a view that was gone, so
  `/` threw *View [welcome] not found* on a site the installer had just built. Keep both and
  the welcome route shadows the homepage, because `/` is the page whose slug is `/`, not a
  static view.

  Neither half survives installing the template, so it is one question defaulting to yes —
  which is what the route prompt's own hint had been arguing while the prompt defaulted to no.

- **The templates are written in English; every other language is a translation.** The views
  used to say `__('Filter op label')` with a `lang/en.json` mapping Dutch keys to English —
  Dutch was the source language, baked into the Blade, so English was the only translation
  that could ever exist. The picker offered German, French, Spanish, Italian, Portuguese and
  Polish and shipped nothing for any of them: a German site rendered German content with a
  Dutch interface.

  The source strings are now English, and `lang/nl.json`, `de`, `fr`, `es`, `it`, `pt` and
  `pl` each translate all 29 of them. English ships no file at all — Laravel falls back to
  the key, which is the string.

  *The six non-Dutch translations are mine, not a native speaker's. They are worth a read.*

- **The translations question comes after the language question, and names the language.**
  "Copy English translations?" was asked before the picker had run, about a language you may
  not have chosen. It now asks once the languages are known, once per chosen language —
  "Copy Nederlands translations?" — and skips English. An unknown code (an extra locale you
  typed) says so instead of silently shipping nothing.

- **The language picker pre-selects `APP_LOCALE`** instead of hardcoding Dutch, so it agrees
  with the project you are standing in. It only seeds the suggestion: `leap.locales` decides
  which locale is unprefixed (see leap 0.10.8), and the installer writes `APP_LOCALE` back to
  match what you picked.

- **The content-type question dropped its parenthetical into the hint.** The label is now
  just "Which content types?", with the archetypes, the `Name:archetype:plural` form and the
  empty-for-none option explained underneath instead of crammed into the question.

### Added

- **The installer patches the User model** instead of printing four lines for you to add by
  hand. It already patched `routes/web.php`, `DatabaseSeeder` and `config/leap.php`, so the
  homework was out of step — and without `HasRoles`, `TwoFactorAuthenticatable` and the
  passkey pair, `/admin` has no roles, no 2FA and nobody can log in with a passkey.

  It asks first (and `--fresh` answers yes, like everything else). `User.php` is the most
  edited file in an app, so it follows `registerPageSeeder`: build the patch, only ask when
  it applies, and fall back to telling you what to add when the file is not a shape it
  recognises. An existing `implements` clause is kept.

  Everything is inserted in sorted position rather than appended, because pint's laravel
  preset orders both imports and trait names — a file the installer wrote has to survive the
  project's own `pint --test`. There is a test that runs pint over the result.

- **`--no-install`.** Skips the `composer require` for the packages the template needs and
  prints the command instead. `--fresh` means yes to everything, and that included reaching
  out to Packagist — right for an install, wrong for anything that has to be repeatable
  offline.

- **A test that every stub is valid PHP, in both of its forms.** The stubs are the product
  and nothing parsed them: they are copied as text, so a syntax error shipped green and
  surfaced in someone else's project. A duplicate import introduced while editing one got
  past a full suite run — this is the check that caught it.

  A `{{#tags}}` block renders two ways (the inner text kept, or the whole block dropped), so
  both are checked: a stub could be fine with tags and broken with `--no-tags`, and only one
  of those was ever rendered. Placeholders are filled in first, since that is the only form
  a project ever sees.

- **A test for `--fresh`.** It had none, so every change to the interactive flow was a guess
  about the unattended one. It now runs a full `--fresh --no-install` install and expects no
  prompts at all: a single question reaching the console fails it, which is what `--fresh`
  means. Verified by letting one prompt escape.

- **Every `leap:template` prompt now explains itself.** Each question carries a one-line hint
  under it, saying what the thing is for or what saying no costs — "Copy PageController?" is
  only obvious to someone who already knows the template. Uses Laravel Prompts' `hint:`, the
  way the language picker already did.

  Overwrite prompts get a different line: when the file already exists, what is at stake is
  your copy, not what the file does, so it says that instead.

  The hint is a required argument on `auto()`, `confirmStep()` and `copyOrReplace()`, so a
  prompt added without one does not compile past review — a question nobody can answer is
  the bug, not the missing string.

- **A test that every shipped language translates every source string, and translates nothing
  that no longer exists.** Nothing tied the two together: `en.json` covered 9 of 29 strings,
  leaving the search box, the video section and the slider in the source language on an
  English site, and nobody noticed. Verified by removing a translation on purpose.

### Fixed

- **The seeder is not registered when it was not copied.** `registerPageSeeder` only checked
  that `DatabaseSeeder.php` existed, never `PageSeeder.php`. Answer no to "Copy PageSeeder?"
  and yes to "Register PageSeeder in DatabaseSeeder?" — which defaulted to yes — and
  `DatabaseSeeder` called a class that was not there, so `php artisan db:seed` fatally failed
  on the whole project rather than skipping the sample pages.

- **The sample content is not offered when there is nothing to seed into.** Migrations and
  seeding were independent questions, so no to the first and yes to the second ran `db:seed`
  against a `pages` table that had never been created. It is only asked when the migrations
  ran, or when the table is already there — someone may well have migrated by hand. Declined,
  it prints the `db:seed` command to run later.

- **`PageSeeder` seeds the languages the site has, and only those.** Two bugs that met in
  the middle.

  The tags were created with a plain string (`'name' => 'Algemeen'`) on a translatable
  field, so Spatie stored it under whichever locale happened to be active while seeding and
  the filter chips above every other overview read Dutch. `TagFactory` had it too.

  Everything else was hardcoded to `nl` and `en` regardless of what you picked, so a German
  site got pages carrying Dutch and English titles and no German at all — rows of text that
  render nowhere, cannot be reached from the admin's language switcher, and stay forever.

  The seeds now carry every language `leap:template` can install, and `PageSeeder::forSite()`
  strips each translation set to the site's own locales before writing, falling back to
  English for a language the seeds have no text for.

  The content-type factories had the same plain-string bug on `title` and `intro`, which are
  translatable — so seeded items existed in one language and every other language of the
  admin showed them blank, while the overview page beside them was filled in. They now repeat
  the placeholder text across the site's locales, which is what `$translate()` in the same
  seeder already did. The text stays identical on purpose: it is nonsense prose, and faking a
  different sentence per language would read as a translation that says something else.

- **A re-run no longer resets `APP_LOCALE` on a hand-configured site.** The installer wrote
  it unconditionally, including on the branch where it had just decided to leave
  `leap.locales` untouched — so `leap:template` on a site deliberately running an English
  admin over a Dutch site quietly reset it to the first locale. The two settings mean
  different things (`leap.locales` decides the site's URLs, `APP_LOCALE` the admin, console,
  queues and mail — see leap 0.10.8), so once the languages were configured by hand, both are
  left alone and the warning says so.

- **`SearchTest` and `SeoTest` are actually installed.** Both shipped as stubs from the
  start and were never copied, nor listed in the manifest `--diff` reads — while testing
  features the template does ship: the live search and the SEO tags.

- **`--diff` no longer reports six phantom new files.** Translations are per site, so a lang
  file the project does not have was never meant to be there; only the ones it has are
  compared against the stubs.

## [0.10.8] — 2026-07-16

### Changed

- **The models use the package's traits directly; three stubs are gone.**
  `App\Traits\HasSections`, `App\Traits\HasSlug` and `App\Support\Video` are no longer
  copied into a project. `HasSections`' 70 lines of behaviour moved into
  `NickDeKruijk\Leap\Traits\HasSections` (leap 0.10.7, now the minimum requirement), joining
  `HasSlug` and `Classes\Video`, which were already there — and the models and the video
  section now reference all three straight from the package.

  `HasSections` never belonged in a project. It reads what Leap's own sections editor writes
  — the JSON shape, the `Mediable` rows, `_sort`/`_name`, `leap.locales` — so it has to move
  whenever the editor does, and as a copied stub it could not: the monolingual crash fixed in
  0.10.4 reached no existing site for that reason.

  The other two were already package behaviour behind a one-line wrapper whose only purpose
  was a central override point. That was speculative — no project has ever used it — and it
  cost a stub to copy, a prompt to answer, a file that could drift, and a second file to open
  before reaching the code. A project that wants a central override can still add the trait
  itself; a project that wants to override one model can define the method on that model.

  `App\Traits\HasTags` stays a stub: it hangs off `App\Models\Tag`, which is itself a stub
  and optional (`--no-tags`).

  **Upgrading:** nothing breaks. Your `App\Traits\*` and `App\Support\Video` keep working as
  long as your models reference them, and `leap:template` will not remove them. To pick this
  up, accept the overwrite of `app/Models/Page.php` (and re-run `leap:content` for generated
  types), then delete the orphaned files. Check `leap:template --diff` first if you edited
  any of them.

### Added

- **A test that a generated model's imports are in pint's order.** Pint formats the `.php`
  stubs in this repo but cannot see `Model.stub`, so nothing caught an unsorted import block
  — which would leave every scaffolded project failing a style check on a file it never
  wrote. Verified by breaking a stub on purpose.
- **A test that the package classes a generated model imports actually exist.** The stubs
  name them as plain text, so nothing tied them to the leap version `composer.json` requires:
  a renamed trait, or one that only ever existed on a branch, would produce a model that
  reads perfectly and fatals on use. The suite could not see it — it never loaded what it
  generated, and was green against a leap without `HasSections` in it. Also verified by
  breaking a stub on purpose.

## [0.10.7] — 2026-07-16

### Changed

- **The installer no longer asks to create directories.** Seven `Create <dir> directory?`
  prompts are gone; `copyOrReplace` creates the destination itself, silently, and only when
  the copy is actually going ahead — the same thing `copyDir` already did. They were two
  questions for one decision, and the no branch was a trap: `copy()` fails when the parent
  is missing, and the return value went unchecked, so the install answered "no" to the
  directory and then still reported `Copied public/css/tinymce.css` for a file that was
  never written. A failing copy or mkdir now says so.

## [0.10.6] — 2026-07-16

### Changed

- **The template no longer installs a `config/minify.php`.** It only existed to override two
  broken defaults in `nickdekruijk/minify` — relative import paths that resolve only when the
  working directory is `public/`, and `testing` in `skip_environment`, which left the suite
  reading whatever build an earlier browser request had left behind. Both are fixed at the
  source in minify 4.0, so every project gets them through `composer update` instead of a
  frozen copy per site. `leap:template` now requires `nickdekruijk/minify:^4.0`.

  Existing installs can delete their `config/minify.php` after upgrading to minify 4.0; a
  published config still wins, so keeping it is harmless but leaves you maintaining it.

## [0.10.5] — 2026-07-15

### Changed

- **Admin modules order like the frontend menu.** Each generated content type's Leap
  resource derives its nav priority from its `leap.content` registry position, so the
  `/admin` sidebar lists Page first, then the content types in `--models` order, then Tags
  (moved to priority 40, just before the file manager at 50, leaving room to slot other
  modules in between).

## [0.10.4] — 2026-07-15

### Fixed

- **Monolingual sites no longer crash on section fields.** The seeders always ship every
  locale for translatable section fields; `HasSections` only collapsed them to the current
  locale when `leap.locales` was set, so a monolingual install echoed an array and threw
  `htmlspecialchars(): ... array given`. It now resolves them in monolingual mode too.
- **`leap:template --fresh` actually seeds the content types.** Migrations, `vendor:publish`
  and the seed now run as fresh subprocesses, so packages `composer require`d during the
  same run (the settings migration/config) and the `leap.content` registry that
  `leap:content` just wrote are visible — previously the settings table stayed unmigrated
  (a 500 on the homepage) and every content type was skipped at seed time.
- **Content is seeded under the chosen locale.** The seed subprocess no longer inherits the
  installer's stale `APP_LOCALE`, so overview pages and items are stored under the locale
  picked in `--locales`, not Laravel's default.
- **`leap:content` registers into the real registry, in order.** Its patch matched the
  `'content' => [`	in the doc-comment example instead of the array, leaving the registry
  empty; it now anchors to the real array and **appends** (so `--models=News,Event` lists
  news before events).
- **Re-running no longer stacks duplicate migrations.** `leap:content` reuses an existing
  `*_create_<table>_table.php` instead of writing a new timestamped one, which had made a
  second `leap:template --fresh` fail to migrate with "table already exists".

### Changed

- **Menu/section/teaser order follows the command.** `leap:template --fresh` reorders
  `leap.content` to the `--models` order (other registered types kept after), each content
  type's overview page takes a `sort` from its registry position, and those sorts are
  realigned on every re-seed — so a re-run with a different `--models` order moves the menu.
- **"About us" and "Contact" sit last in the menu**, after the content-type overviews.

## [0.10.3] — 2026-07-15

### Added

- **Composer suggests `--dev` at install time.** Added the `dev` keyword, so
  `composer require nickdekruijk/leap-template` without `--dev` prints "recommended to be
  placed in require-dev" and offers to re-run with `--dev` — the same nudge debugbar and
  other dev tools give (Composer matches the `dev`/`testing`/`static analysis` keywords).

### Removed

- **The runtime "install with `--dev`" console warning** (`warnIfNotDev`, added in 0.10.1,
  reworked in 0.10.2). Composer's own keyword prompt above covers it, and a package
  inspecting its own install topology at runtime was needless. Drops the composer.lock
  read with it.

## [0.10.2] — 2026-07-15

### Fixed

- **The non-dev install warning no longer needs Composer 2.2+.** It called
  `Composer\InstalledVersions::isDevRequirement()`, which throws "Call to undefined
  method" on older Composer. It now reads `composer.lock` (packages vs packages-dev)
  directly, so it works on every Composer version. The `composer-runtime-api` requirement
  is dropped (no longer used).

## [0.10.1] — 2026-07-15

### Added

- **Console warning when installed without `--dev`.** This is dev-only tooling, so it
  should not ship to production; detected via
  `Composer\InstalledVersions::isDevRequirement()` (adds a `composer-runtime-api: ^2.0`
  requirement), shown on the console only and never while developing this package itself.

### Changed

- Dropped the local path repository and `minimum-stability: dev` from `composer.json`
  now that `nickdekruijk/leap` 0.10.0 is on Packagist — leap resolves as a normal stable
  dependency.

## [0.10.0] — 2026-07-15

### Added

- Initial release. The frontend website template scaffolding — `leap:template` and
  `leap:content` plus their stubs — extracted from `nickdekruijk/leap` into this
  dev-only package, so it has no production footprint (`composer install --no-dev`).
- Requires `nickdekruijk/leap: ^0.10` (which must be a normal, non-dev requirement of
  the host app — it is the runtime admin panel).
- Both commands refuse to run on production without `--force`.

See the [leap docs](https://github.com/nickdekruijk/leap/blob/main/docs/content-types.md)
for the template and content-type documentation.
