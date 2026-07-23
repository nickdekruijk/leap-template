# Leap Template

Frontend website template scaffolding for [`nickdekruijk/leap`](https://github.com/nickdekruijk/leap):
the `leap:template` and `leap:content` commands, plus the stubs they copy into your
project. It is **dev-only tooling** — install it with `--dev` so it (and its commands)
are absent from production entirely.

## Installation

Leap itself is a **runtime** dependency (the admin panel runs in production); only the
scaffolding is dev-only. So install **two** packages, like Laravel's own framework + Pint:

```bash
composer require nickdekruijk/leap                 # admin panel — production (non-dev)
composer require --dev nickdekruijk/leap-template  # scaffolding — dev only
```

> **Why `nickdekruijk/leap` must be a normal (non-dev) require:** this package depends on
> leap, but if you only `require --dev leap-template`, leap comes in as a *dev-transitive*
> dependency and `composer install --no-dev` on production removes it — taking the admin
> panel with it. leap has to be a direct non-dev requirement of your app.

On production (`composer install --no-dev`) `leap-template` is not installed, so
`leap:template`/`leap:content` don't exist there — zero footprint.

## Live demo

[leap.nickdekruijk.nl](https://leap.nickdekruijk.nl) is a stock `leap:template` install —
that site is what these stubs produce. Log in on
[/admin](https://leap.nickdekruijk.nl/admin) with `info@example.com` / `leapdemo` and
change anything; the site resets to its seeded state 15 minutes after the last change.

## Commands

```bash
php artisan leap:template          # scaffold the frontend website (interactive)
php artisan leap:template --fresh  # complete, unattended install (implies --force)
php artisan leap:content News      # generate a listed content type
```

Both refuse to run on `APP_ENV=production` without `--force` (like Laravel's `migrate`).

## Documentation

The template and content types are documented in the leap repo:

- [Frontend template](https://github.com/nickdekruijk/leap/blob/master/docs/template.md)
- [Content types (news/events/…)](https://github.com/nickdekruijk/leap/blob/master/docs/content-types.md)

## Versioning

Tracks leap's major line — this package requires `nickdekruijk/leap: ^1.0`, so a breaking
leap release (2.0) is paired with a new `leap-template` major. Minor and patch versions
are independent: the two packages are released in lockstep at the same version number,
but you can update either on its own within the major.

## License

MIT.
