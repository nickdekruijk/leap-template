# Changelog

All notable changes to `nickdekruijk/leap-template` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
