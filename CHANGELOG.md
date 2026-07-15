# Changelog

All notable changes to `nickdekruijk/leap-template` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
