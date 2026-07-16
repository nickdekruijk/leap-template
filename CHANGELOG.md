# Changelog

All notable changes to `nickdekruijk/leap-template` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
