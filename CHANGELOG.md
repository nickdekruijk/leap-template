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

- **Every `leap:template` prompt now explains itself.** All 32 questions carry a one-line
  hint under them, saying what the file is for or what saying no costs — "Copy
  PageController?" is only obvious to someone who already knows the template. Uses Laravel
  Prompts' `hint:`, the way the language picker already did.

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
