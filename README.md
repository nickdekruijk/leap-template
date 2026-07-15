# Leap Template

Frontend website template scaffolding for [`nickdekruijk/leap`](https://github.com/nickdekruijk/leap):
the `leap:template` and `leap:content` commands, plus the stubs they copy into your
project. It is **dev-only tooling** — install it with `--dev` so it (and its commands)
are absent from production entirely.

## Installation

Leap itself is a **runtime** dependency (the admin panel runs in production); only the
scaffolding is dev-only. So install **two** packages, like Laravel's own framework + Pint:

```bash
composer require nickdekruijk/leap -W              # admin panel — production (non-dev)
composer require --dev nickdekruijk/leap-template  # scaffolding — dev only
```

> **Why `nickdekruijk/leap` must be a normal (non-dev) require:** this package depends on
> leap, but if you only `require --dev leap-template`, leap comes in as a *dev-transitive*
> dependency and `composer install --no-dev` on production removes it — taking the admin
> panel with it. leap has to be a direct non-dev requirement of your app.

> **Why `-W` on the leap install:** leap's passkey chain (`spomky-labs/cbor-php`) caps
> `brick/math` at `^0.17`, but a fresh Laravel locks it to `0.18`. Without `-W`, Composer
> won't downgrade the locked `brick/math` and silently installs an ancient Leap instead of
> erroring. Drop `-W` once `cbor-php` supports newer `brick/math`.

On production (`composer install --no-dev`) `leap-template` is not installed, so
`leap:template`/`leap:content` don't exist there — zero footprint.

## Commands

```bash
php artisan leap:template          # scaffold the frontend website (interactive)
php artisan leap:template --fresh  # complete, unattended install (implies --force)
php artisan leap:content News      # generate a listed content type
```

Both refuse to run on `APP_ENV=production` without `--force` (like Laravel's `migrate`).

## Documentation

The template and content types are documented in the leap repo:

- [Frontend template](https://github.com/nickdekruijk/leap/blob/main/docs/template.md)
- [Content types (news/events/…)](https://github.com/nickdekruijk/leap/blob/main/docs/content-types.md)

## Versioning

Tracks leap's major/minor line — this package requires `nickdekruijk/leap: ^0.10`, so a
breaking leap release (0.11) is paired with a new `leap-template` release. Patch versions
are independent.

## License

MIT.
