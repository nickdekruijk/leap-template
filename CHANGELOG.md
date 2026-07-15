# Changelog

All notable changes to `nickdekruijk/leap-template` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
