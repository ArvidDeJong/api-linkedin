---
title: "Installation & configuration"
description: "Install darvis/api-linkedin: requirements, setting up the LinkedIn app and its products, environment variables and the config file."
nav_order: 2
---

# Installation & configuration

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- An `APP_KEY`: the access and refresh tokens are stored encrypted
- A LinkedIn app with the **Share on LinkedIn** product for the personal profile, and the **Community Management API** product for company pages

## Installation

```bash
composer require darvis/api-linkedin
php artisan migrate
```

The service provider and the `LinkedIn` facade are registered automatically through package discovery, and the migration for the `linkedin_accounts` table is loaded from the package. Publishing is optional:

```bash
php artisan vendor:publish --tag=linkedin-config
php artisan vendor:publish --tag=linkedin-migrations
```

## Setting up the LinkedIn app

1. Create an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps).
2. Under **Products**, request **Share on LinkedIn** (personal profile) and, for company pages, **Community Management API**.
3. Under **Auth**, add the redirect URL: `https://your-domain.test/linkedin/callback`, or your own callback route. It must match exactly; see [the redirect URI](#the-redirect-uri) below.
4. Copy the client ID and secret into `.env`.

```dotenv
LINKEDIN_CLIENT_ID=...
LINKEDIN_CLIENT_SECRET=...

# Company page to post on by default. Leave empty to post on the profile only.
LINKEDIN_ORGANIZATION_URN=urn:li:organization:1234567

# A valid, recent LinkedIn API version (format YYYYMM)
LINKEDIN_API_VERSION=202601
```

LinkedIn versions its REST API monthly and each version is valid for about a year. A publish that suddenly starts failing is often an expired `LINKEDIN_API_VERSION`, not a bug.

## The redirect URI

The `redirect_uri` sent to LinkedIn is not configured but derived: it is the URL of the route named in `linkedin.routes.callback_name` (`linkedin.callback` by default). Whatever that route resolves to must be the redirect URL in the LinkedIn app, character for character, including the scheme and a trailing slash or its absence.

When you turn the built-in routes off and wire the flow yourself, your own callback route must carry that name, or the `redirect_uri` in the two OAuth calls will not match and LinkedIn refuses the code exchange.

## Configuration

All keys live in `config/linkedin.php`.

| Key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `client_id` | `LINKEDIN_CLIENT_ID` | | Client ID of the LinkedIn app |
| `client_secret` | `LINKEDIN_CLIENT_SECRET` | | Client secret of the LinkedIn app |
| `organization_urn` | `LINKEDIN_ORGANIZATION_URN` | `null` | Default company page. Empty means profile only, and `w_organization_social` is then not requested |
| `api_version` | `LINKEDIN_API_VERSION` | `202601` | LinkedIn API version, format `YYYYMM` |
| `scopes` | | `[]` | Extra scopes on top of `openid`, `profile` and `w_member_social` |
| `organizations.enabled` | `LINKEDIN_ORGANIZATIONS_ENABLED` | `false` | Let `LinkedIn::organizations()` list company pages; adds `r_organization_admin` and `w_organization_social` to the authorization |
| `organizations.cache_ttl` | `LINKEDIN_ORGANIZATIONS_CACHE_TTL` | `3600` | Seconds to cache that list, `0` disables the cache |
| `table` | | `linkedin_accounts` | Name of the accounts table, read by the model and the migration |
| `routes.enabled` | `LINKEDIN_ROUTES_ENABLED` | `true` | Register the built-in connect and callback routes |
| `routes.prefix` | `LINKEDIN_ROUTE_PREFIX` | `linkedin` | URL prefix of those routes |
| `routes.middleware` | | `['web']` | Middleware of those routes |
| `routes.connect_name` | | `linkedin.connect` | Name of the connect route |
| `routes.callback_name` | | `linkedin.callback` | Name of the callback route; also decides the `redirect_uri` |
| `routes.redirect_to` | | `null` | Route name to send the user back to after the flow; `null` redirects to `/` |
| `session.*` | | | Session keys for the CSRF state, the requested scopes and the flash messages |

Two settings change which scopes are requested: `organization_urn` (adds `w_organization_social`) and `organizations.enabled` (adds `r_organization_admin` as well). Changing either after connecting does not change the stored token; see [Connecting](connecting.md#the-config-asks-the-token-decides).

## Testing your integration

All LinkedIn calls go through `Illuminate\Support\Facades\Http`, so `Http::fake()` intercepts them in your tests. The publish response carries the post URN in the `x-restli-id` header, not in the body, so fake that header when you assert on the returned URN:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:123']),
]);
```
