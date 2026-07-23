# Backlog

Larger refactors deliberately left out of 0.10.18 (installer/stub hardening release)
because each is big or risky enough to deserve its own PR and review. Recorded here so
they are not forgotten. Line references are omitted on purpose — they drift; the file
and method names are the anchor.

## TemplateCommand.php — decompose the god class
`src/Commands/TemplateCommand.php` is ~2000 lines doing roughly ten unrelated jobs. Split
into `src/Support/` classes, each testable on its own:

- `UserModelPatcher` — the PHP-source patcher (already isolated-tested via `UserModelPatchTest`)
- `TemplateDiff` — the whole `--diff` path (diff report, missing keys/columns, install steps)
- `StubInstaller` — the copy/replace/dir/delete file primitives
- `LocaleConfigurator` — locale resolution, translation install, framework-translation install
- `SeederPatcher` — `registerPageSeeder` + `unmuteModelEvents`
- `RoutePatcher` — `serveFromThePageTree`, `routeExists`, `importPageController`

The command then becomes a thin orchestrator.

**Why deferred:** the installer is the package's main entry point; safest to extract one
unit at a time with its own tests.

## Move the page-tree engine into leap (largest, cross-package)
The stub `stubs/template/app/Http/Controllers/PageController.php` (~700 lines) is a generic
page-tree engine — routing, sitemap, breadcrumbs, navigation, locale paths — not a
per-project controller. Because it is a copied stub, a bugfix here never reaches existing
projects via `composer update`: the exact anti-pattern the `HasSlug` split in leap avoids
(the trait lives in the package, the project ships a thin wrapper).

Move the engine into `nickdekruijk/leap` as a `PageTree` service and leave a thin
`App\Http\Controllers\PageController` that delegates. Fold in while doing it:

- **Dedupe with `HasLocaleRouting`** — the sitemap hreflang assembly and the per-locale URL
  switcher are duplicated almost line-for-line between the stub and leap's `HasLocaleRouting`.
- **One slug-path builder** — there are four variants that all compute a page's path from its
  slug chain (`localePath`, `buildLocalePath`, `Search::resolvePageUrl`, the `getPages`
  traverse). Collapse to one map-based helper (no N+1).
- **N+1 in `breadcrumbs()`** — it walks the parent chain with `Page::find()` per crumb.
- **`Page::localeSlug()` accessor** — the `getTranslation('slug', $locale, false)` call is
  open-coded ~12 times across `PageController` and `Search`; wrap the "no fallback, empty =
  not routable" rule in one accessor.

**Why deferred:** it is an architecture move that touches both packages and every generated
project; needs its own PR with its own tests, mirroring the `HasSlug` package/stub split.

## Test gaps
- `installFrameworkTranslations` repair path (`--no-install` on a stale bare-stub, and the
  "package not installed + stale" union) — only the happy re-run merge is covered today.
- The MySQL branch of `Search::localeColumnExpr` — the search test runs on sqlite only, so
  the `JSON_UNQUOTE/JSON_EXTRACT` expression is never executed.
- `ContentDeleteCommand` deeper destructive paths — `deleteOverviewPage`'s `forceDelete`,
  the tag-link cleanup and the migrations-row removal are not asserted (the test stops at
  files + registry).
