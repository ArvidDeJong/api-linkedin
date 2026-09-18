---
title: Connecting
description: "Connect a LinkedIn account to a Laravel app: the built-in OAuth routes, wiring the flow yourself, apps without the Community Management API, and reading what the token may do."
nav_order: 3
---

# Connecting

The package keeps **one connection for the whole application**, not one per user: `LinkedIn::account()` returns the most recently stored account and `LinkedIn::disconnect()` removes it. A multi-tenant setup needs its own retrieval path and is out of scope.

## The built-in routes

Send the user to the connect route; LinkedIn asks for consent and returns to the callback, which stores the connection:

```php
$url = route('linkedin.connect');
```

Afterwards the user is redirected to the route named in `linkedin.routes.redirect_to`, or to `/` when that is empty, with a flash message in `session('linkedin_status')` on success or `session('linkedin_error')` on failure. The callback verifies the OAuth `state` against the session and rejects a mismatch.

The routes live under the prefix and middleware from `linkedin.routes` (`/linkedin/connect` and `/linkedin/callback` by default, in the `web` group). Put your own middleware in front of them when only some users may connect the account.

## Wiring the flow yourself

Set `linkedin.routes.enabled` to `false` and use the two manager methods. Your callback route must carry the name from `linkedin.routes.callback_name`, because the [redirect URI](installation.md#the-redirect-uri) is derived from it:

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Illuminate\Support\Str;

// Step 1: send the user to LinkedIn
$state = Str::random(40);
session(['linkedin_oauth_state' => $state]);

return redirect()->away(LinkedIn::authorizationUrl($state));

// Step 2: in your callback, after checking the state
$account = LinkedIn::connectFromCode((string) $request->string('code'));
```

## Apps without the Community Management API

LinkedIn refuses the **entire** authorization when a single requested scope is not authorized for your app; the member never even reaches the consent screen. An app that only holds Share on LinkedIn therefore cannot connect at all while the config asks for company page scopes.

Ask for the member scopes only, and it works on any app:

```php
// With the built-in routes
$url = route('linkedin.connect', ['profile_only' => 1]);

// With your own flow: pass the same set to both calls, so the granted
// scopes are recorded truthfully when LinkedIn leaves `scope` out of the token
use Darvis\ApiLinkedin\Scopes;

$url = LinkedIn::authorizationUrl($state, Scopes::MEMBER);
$account = LinkedIn::connectFromCode($code, Scopes::MEMBER);
```

Read a refused authorization instead of parsing LinkedIn's English text, which arrives HTML-escaped and would show entities when echoed into Blade:

```php
use Darvis\ApiLinkedin\AuthorizationDenial;

if ($denial = AuthorizationDenial::fromCallback($request)) {
    $denial->description;                     // decoded, human readable
    $denial->isScopeProblem();                // your app lacks a product; retrying is pointless
    $denial->missingScope();                  // 'w_organization_social'
    $denial->isRecoverableWithMemberScopes(); // offer the profile-only connect
}
```

## The config asks, the token decides

The config says which scopes are *requested*; the stored token records which scopes LinkedIn *granted*. They drift apart the moment the LinkedIn app misses a product, or when you change `organization_urn` or `organizations.enabled` after connecting. The token always wins, so gate your UI on the connection and never on the config alone, or you offer targets that publish into a 403:

```php
use Darvis\ApiLinkedin\Scopes;

LinkedIn::isConnected();
LinkedIn::canPostAsOrganization();  // the token carries w_organization_social
LinkedIn::canListOrganizations();   // config allows it AND the token carries r_organization_admin

$account = LinkedIn::account();
$account->grantedScopes();          // ['openid', 'profile', 'w_member_social'], or null when unknown
$account->hasScope(Scopes::POST_AS_ORGANIZATION);
$account->lacksScope(Scopes::LIST_ORGANIZATIONS);
```

`hasScope()` and `lacksScope()` are not each other's negation. A connection stored before 1.4 has no recorded scopes: `grantedScopes()` is then `null` and both methods return `false`. The package refuses to guess in either direction, so nothing is offered that it cannot promise and nothing is blocked that might still work.

A token never gains scopes. When a call needs a scope the token lacks, the package throws [`LinkedInScopeMissing`](errors.md) before any request goes out, and the only fix is to add the product to the LinkedIn app and reconnect.

## Token lifecycle

Every publish first asks for a fresh access token. An expired token is refreshed automatically with the refresh token, with a one minute margin; when no usable refresh token is left, the package throws `LinkedInConnectionExpired`, the one failure an end user can fix by connecting again. Never read `$account->access_token` directly in your own code.
