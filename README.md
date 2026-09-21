# darvis/api-linkedin

[![Latest Version](https://img.shields.io/packagist/v/darvis/api-linkedin.svg)](https://packagist.org/packages/darvis/api-linkedin)
[![Tests](https://github.com/ArvidDeJong/api-linkedin/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/api-linkedin/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/darvis/api-linkedin/php.svg)](composer.json)
[![License](https://img.shields.io/packagist/l/darvis/api-linkedin.svg)](LICENSE)

A Laravel package that publishes posts on **LinkedIn**, on the personal profile of the
connected member or on a company page, through OAuth 2.0 and the LinkedIn Posts API. It
handles the connect flow, stores the tokens encrypted and refreshes them. No SDK, no
Socialite provider: every call goes through Laravel's built-in HTTP client.

An independent open-source package, not affiliated with LinkedIn.

## Features

- OAuth 2.0 authorization code flow with ready-made connect and callback routes, or routes of your own
- Tokens stored encrypted in a `linkedin_accounts` table, and renewed with the refresh token when LinkedIn issued one
- Post as the member (`w_member_social`) or as a company page (`w_organization_social`)
- List the company pages the member administers and pick one per post
- Article cards with your own title, description and uploaded thumbnail
- Records which scopes LinkedIn granted, and refuses a call that is bound to fail before sending it
- Typed exceptions, including one for a timeout, so your code branches on the type instead of on a message
- Ships a Laravel Boost guideline and skill

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- A LinkedIn app with the products **Sign In with LinkedIn using OpenID Connect** and
  **Share on LinkedIn**; posting on a company page also needs the **Community Management API**

## Installation

```bash
composer require darvis/api-linkedin
php artisan migrate
```

Create an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps),
add the redirect URL `https://your-domain.test/linkedin/callback`, and fill in `.env`:

```dotenv
LINKEDIN_CLIENT_ID=your-client-id
LINKEDIN_CLIENT_SECRET=your-client-secret
```

[Installation & configuration](https://arviddejong.github.io/api-linkedin/installation.html)
has every step and a way to check that it works.

## Who may connect

Whoever completes the connect flow becomes the **one connection the whole application posts
with**. The connect and callback routes therefore run through `['web', 'auth']` by default.
Signed in is usually still too wide: publish the config and narrow `linkedin.routes.middleware`
to the people who may do this, for example `['web', 'auth', 'can:manage-linkedin']`. A
`config/linkedin.php` published before 1.8 still says `['web']`; change it. See
[Who may connect](https://arviddejong.github.io/api-linkedin/connecting.html#who-may-connect).

## Quick start

Send a signed-in user to `route('linkedin.connect')` once. After that, publish from anywhere
in your application, preferably from a queued job:

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

$result = LinkedIn::postAsMember("New blog article!\n\nhttps://example.com/blog/my-article");

// $result['urn']        the id LinkedIn gave the post
// $result['permalink']  https://www.linkedin.com/feed/update/<urn>/
```

The [quick start](https://arviddejong.github.io/api-linkedin/quickstart.html) has the complete
example: the settings page, the flash messages and the job.

## Documentation

Full documentation: **https://arviddejong.github.io/api-linkedin/**

| Page | |
| --- | --- |
| [Installation & configuration](https://arviddejong.github.io/api-linkedin/installation.html) | The LinkedIn app, the `.env` values, every config key, checking that it works |
| [Quick start](https://arviddejong.github.io/api-linkedin/quickstart.html) | One complete example, from a connect button to a published post |
| [Connecting](https://arviddejong.github.io/api-linkedin/connecting.html) | Who may connect, the flash messages, your own OAuth routes, what the token may do |
| [Publishing](https://arviddejong.github.io/api-linkedin/publishing.html) | Posts on a profile or a company page, article cards with your own image |
| [Company pages](https://arviddejong.github.io/api-linkedin/company-pages.html) | Listing the pages a member administers, the scope, the cache |
| [Error handling](https://arviddejong.github.io/api-linkedin/errors.html) | The exception types and what to do with each |
| [Troubleshooting](https://arviddejong.github.io/api-linkedin/troubleshooting.html) | Look up a message, find the cause and the fix |
| [Testing](https://arviddejong.github.io/api-linkedin/testing.html) | Test your own code without calling LinkedIn |
| [FAQ](https://arviddejong.github.io/api-linkedin/faq.html) | Short answers to common questions |

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline and an
`api-linkedin-development` skill. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only
composer format    # Pint, fixes
composer analyse   # Larastan, level 8
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Found a security problem? Please report it privately; see [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
