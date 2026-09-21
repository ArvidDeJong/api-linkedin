# darvis/api-linkedin

[![Latest Version](https://img.shields.io/packagist/v/darvis/api-linkedin.svg)](https://packagist.org/packages/darvis/api-linkedin)
[![Tests](https://github.com/ArvidDeJong/api-linkedin/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/api-linkedin/actions/workflows/tests.yml)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![Total downloads](https://img.shields.io/packagist/dt/darvis/api-linkedin.svg)](https://packagist.org/packages/darvis/api-linkedin)
[![License](https://img.shields.io/packagist/l/darvis/api-linkedin.svg)](LICENSE)

Publish posts on **LinkedIn** from a Laravel application, on the connected member's
personal profile or on a company page, through OAuth 2.0 and the LinkedIn Posts API.
No SDK, no Socialite provider, no Guzzle wrapper: everything goes through Laravel's
built-in HTTP client.

An independent open-source package, not affiliated with LinkedIn.

## Features

- **OAuth 2.0** authorization code flow with ready-made connect and callback routes, or wire the flow yourself
- **Automatic token refresh**, with the tokens stored encrypted in a `linkedin_accounts` table
- **Post as a member** (`w_member_social`) or **as a company page** (`w_organization_social`)
- **List the company pages** the member administers and pick one per post
- **Article cards with your own image**: upload a thumbnail and keep the whole card clickable
- **Knows what LinkedIn granted**: an app without the Community Management API still connects, on the profile
- **Typed exceptions**, so your code branches on the type instead of on LinkedIn's message text

## Installation

```bash
composer require darvis/api-linkedin
php artisan migrate
```

Create an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps),
request the **Share on LinkedIn** product (and **Community Management API** for company
pages), set the redirect URL to `https://your-domain.test/linkedin/callback` and fill in
`.env`:

```dotenv
LINKEDIN_CLIENT_ID=...
LINKEDIN_CLIENT_SECRET=...
LINKEDIN_ORGANIZATION_URN=urn:li:organization:1234567   # optional, company page
LINKEDIN_API_VERSION=202601                              # a valid, recent version (YYYYMM)
```

See [Installation & configuration](docs/installation.md) for every config key and the
redirect URI rules.

## Usage

Send a signed-in user to `route('linkedin.connect')` once, then publish. Whoever completes
that flow becomes the one connection the whole application posts with, so the connect and
callback routes run through `['web', 'auth']` by default; narrow it to the people who may do
this with `linkedin.routes.middleware`, for example `['web', 'auth', 'can:manage-linkedin']`
(see [Connecting](docs/connecting.md#who-may-connect)).

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

// On the connected member's profile
LinkedIn::postAsMember("New blog article!\n\nhttps://example.com/blog/my-article");

// On the default company page, or a specific one
LinkedIn::postAsOrganization('Company news https://example.com');
LinkedIn::postAsOrganization('Company news', 'urn:li:organization:1234567');

// Both return ['urn' => '...', 'permalink' => '...']
```

The config says which scopes are requested; the token says which were granted, and the
token wins. Gate your UI on `LinkedIn::canPostAsOrganization()` and
`LinkedIn::canListOrganizations()`, never on the config alone.

## Documentation

Full documentation: **https://arviddejong.github.io/api-linkedin/**

| Topic | |
| --- | --- |
| [Installation & configuration](docs/installation.md) | The LinkedIn app, environment variables, every config key, the redirect URI |
| [Connecting](docs/connecting.md) | The OAuth routes, wiring the flow yourself, apps without the Community Management API, what the token may do |
| [Publishing](docs/publishing.md) | Posts, article cards with your own image, the services |
| [Company pages](docs/company-pages.md) | Listing the pages a member administers, the scope, the cache |
| [Error handling](docs/errors.md) | The exception types and common causes |

Or start at the [documentation index](docs/README.md), or read the [FAQ](https://arviddejong.github.io/api-linkedin/faq.html).

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline with the
rules that matter when writing code against it, and an `api-linkedin-development` skill
with the publish flow, what every failure gives you, the pitfalls in a host app and how to
test without calling LinkedIn. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Development

```bash
composer test      # Pest
composer lint      # Pint, check only (composer format to fix)
composer analyse   # Larastan, level 8
```

GitHub Actions runs the tests on PHP 8.2 to 8.4 against Laravel 11, 12 and 13, with both
the lowest and the latest allowed dependencies. See the [changelog](CHANGELOG.md) for
release notes.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Found a security problem? Please report it privately, see [SECURITY.md](SECURITY.md).

## Author

**Arvid de Jong** · [info@arvid.nl](mailto:info@arvid.nl)

## License

MIT, see [LICENSE](LICENSE).
