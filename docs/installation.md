---
title: "Installation & configuration"
description: "Install darvis/api-linkedin step by step: create the LinkedIn app and its products, set the .env values, check that it works, and look up every config key."
nav_order: 2
---

# Installation & configuration

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13
- An `APP_KEY` in `.env`. The access and refresh tokens are stored encrypted with it.
- A database. The package adds one table, `linkedin_accounts`.
- A way for users to sign in to your application, with a route named `login`. The connect routes require a signed-in user by default.
- A LinkedIn app, which you create in step 2.

## Step 1. Install the package

```bash
composer require darvis/api-linkedin
php artisan migrate
```

Laravel registers the service provider and the `LinkedIn` facade by itself (package discovery). The migration for the `linkedin_accounts` table is loaded from the package, so `php artisan migrate` creates it without publishing anything.

## Step 2. Create the LinkedIn app

1. Go to [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps) and create an app.
2. Open the **Products** tab and request these products:

   | Product | Gives the scopes | Needed for |
   | --- | --- | --- |
   | Sign In with LinkedIn using OpenID Connect | `openid`, `profile` | Reading the name and id of the member who connects. Always needed. |
   | Share on LinkedIn | `w_member_social` | Posting on the personal profile. Always needed. |
   | Community Management API | `w_organization_social`, `r_organization_admin` | Posting on a company page and listing company pages. LinkedIn reviews this request. |

   A scope is a permission that the member grants to your app. The package always asks for `openid`, `profile` and `w_member_social`.
3. Open the **Auth** tab and add the redirect URL: `https://your-domain.test/linkedin/callback`. It must match the URL of the callback route exactly; see [the redirect URI](#the-redirect-uri).
4. Copy the **Client ID** and the **Client Secret** from the Auth tab.

## Step 3. Fill in `.env`

```dotenv
LINKEDIN_CLIENT_ID=your-client-id
LINKEDIN_CLIENT_SECRET=your-client-secret
```

Two more values are optional:

```dotenv
# Default company page to post on. Leave it out to post on the profile only.
LINKEDIN_ORGANIZATION_URN=urn:li:organization:1234567

# LinkedIn API version, format YYYYMM. The package default is 202601.
LINKEDIN_API_VERSION=202601
```

A URN is LinkedIn's id format. Don't know the URN of your company page? [Company pages](company-pages.md) shows how to let the package list the pages you administer, with their URNs.

Setting `LINKEDIN_ORGANIZATION_URN` makes the package ask for `w_organization_social` too. LinkedIn refuses the whole authorization when your app may not use one of the requested scopes, so only set it when your app has the Community Management API.

LinkedIn releases a new API version every month and supports each for at least a year; see [LinkedIn's versioning page](https://learn.microsoft.com/linkedin/marketing/versioning). When every call suddenly fails with a 4xx status, check whether your `LINKEDIN_API_VERSION` has been retired.

Did you cache the config? Run `php artisan config:clear` after changing `.env`.

## Step 4. Decide who may connect

Whoever completes the connect flow becomes the account your whole application posts with. The built-in routes therefore require a signed-in user. That is usually still too wide, so publish the config and add an ability:

```bash
php artisan vendor:publish --tag=linkedin-config
```

```php
// config/linkedin.php
'routes' => [
    // ...
    'middleware' => ['web', 'auth', 'can:manage-linkedin'],
],
```

[Who may connect](connecting.md#who-may-connect) explains the ability and other guards.

## Check that it works

Run this in your project:

```bash
php artisan route:list --name=linkedin
```

You should see two routes: `GET|HEAD linkedin/connect` with the name `linkedin.connect`, and `GET|HEAD linkedin/callback` with the name `linkedin.callback`.

Then check the credentials and the table:

```bash
php artisan tinker --execute="echo json_encode(['configured' => LinkedIn::isConfigured(), 'connected' => LinkedIn::isConnected()]);"
```

Expected output before anybody connected:

```text
{"configured":true,"connected":false}
```

| You see | It means |
| --- | --- |
| No routes in the list | `LINKEDIN_ROUTES_ENABLED` is `false`, or a cached route list is out of date: run `php artisan route:clear` |
| `"configured":false` | `LINKEDIN_CLIENT_ID` or `LINKEDIN_CLIENT_SECRET` is empty, or the config is cached: run `php artisan config:clear` |
| An error about a missing table `linkedin_accounts` | The migration did not run: `php artisan migrate` |

Now sign in to your application and open `/linkedin/connect` in the browser. LinkedIn asks for consent and sends you back to `/`, with the message `LinkedIn connected as <name>.` in the session. Run the tinker command again and it says `"connected":true`. Something else? See [Troubleshooting](troubleshooting.md).

## The redirect URI

The `redirect_uri` that the package sends to LinkedIn is not a setting. It is the URL of the route named in `linkedin.routes.callback_name`, which is `linkedin.callback` by default. That URL must be listed in the LinkedIn app character for character, including `http` or `https` and the host: `https://example.com` and `https://www.example.com` are two different redirect URLs.

When you turn the built-in routes off and write the flow yourself, give your own callback route that name. Otherwise the two OAuth calls carry a `redirect_uri` that LinkedIn does not know, and it refuses the code exchange.

## Publishing the config and the migration

Both are optional.

```bash
php artisan vendor:publish --tag=linkedin-config      # config/linkedin.php
php artisan vendor:publish --tag=linkedin-migrations  # database/migrations/
```

A published config is a copy. It does not pick up new defaults of a later package version, so compare it with the table below after an upgrade.

## Configuration

All keys live in `config/linkedin.php`.

| Key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `api_version` | `LINKEDIN_API_VERSION` | `202601` | LinkedIn API version, format `YYYYMM`, sent as the `LinkedIn-Version` header |
| `client_id` | `LINKEDIN_CLIENT_ID` | none | Client ID of the LinkedIn app |
| `client_secret` | `LINKEDIN_CLIENT_SECRET` | none | Client secret of the LinkedIn app |
| `organization_urn` | `LINKEDIN_ORGANIZATION_URN` | none | Default company page for `postAsOrganization()`. When set, `w_organization_social` is requested on connect |
| `organizations.cache_ttl` | `LINKEDIN_ORGANIZATIONS_CACHE_TTL` | `3600` | Seconds the list of company pages is cached, `0` turns the cache off |
| `organizations.enabled` | `LINKEDIN_ORGANIZATIONS_ENABLED` | `false` | Request `w_organization_social` and `r_organization_admin` on connect, so `LinkedIn::organizations()` can list company pages; see [Company pages](company-pages.md) |
| `routes.callback_name` | | `linkedin.callback` | Name of the callback route. The `redirect_uri` is the URL of this route |
| `routes.connect_name` | | `linkedin.connect` | Name of the connect route |
| `routes.enabled` | `LINKEDIN_ROUTES_ENABLED` | `true` | Register the built-in connect and callback routes |
| `routes.middleware` | | `['web', 'auth']` | Middleware of both routes; see [Who may connect](connecting.md#who-may-connect). `null` or `[]` removes every middleware, including `web`, and the routes then fail because they need the session |
| `routes.prefix` | `LINKEDIN_ROUTE_PREFIX` | `linkedin` | URL prefix of both routes |
| `routes.redirect_to` | | `null` | Name of the route the user returns to after the flow. `null` redirects to `/` |
| `scopes` | | `[]` | Extra scopes on top of the ones the package requests itself |
| `session.error_key` | | `linkedin_error` | Session key of the flash message after a failed connect |
| `session.scopes_key` | | `linkedin_requested_scopes` | Session key that remembers the requested scopes during the flow |
| `session.state_key` | | `linkedin_oauth_state` | Session key of the OAuth `state` during the flow |
| `session.status_key` | | `linkedin_status` | Session key of the flash message after a successful connect |
| `table` | | `linkedin_accounts` | Name of the accounts table, used by the model and the migration |

Three settings decide which scopes are requested: `organization_urn` adds `w_organization_social`, `organizations.enabled` adds `w_organization_social` and `r_organization_admin`, and `scopes` adds whatever you list. Changing them after connecting does not change the stored token; see [The config asks, the token decides](connecting.md#the-config-asks-the-token-decides).

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline and an `api-linkedin-development` skill, so an AI assistant in your project knows the publish flow, the exceptions, the pitfalls and how to test without calling LinkedIn. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.
