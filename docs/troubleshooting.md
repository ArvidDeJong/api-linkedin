---
title: "Troubleshooting"
description: "Look up an error message of darvis/api-linkedin: the literal flash messages, exception messages and Laravel errors, each with its cause and the fix."
nav_order: 8
---

# Troubleshooting

Find the message you see, then read the cause and the fix. The messages are quoted literally from the package. The flash messages appear in `session('linkedin_error')`; the exception messages appear in `storage/logs/laravel.log`.

Two fixes solve a large share of the problems, so try them first:

```bash
php artisan config:clear   # .env changes are ignored while the config is cached
php artisan route:clear    # route changes are ignored while the routes are cached
```

## Opening the connect route

### I am sent to the login page

**Cause.** The connect and callback routes require a signed-in user since 1.8 (`routes.middleware` is `['web', 'auth']`).

**Fix.** Sign in first. When your administrators sign in through another guard, set that guard's middleware in the published config, for example `['web', 'auth:admin']`.

### `Route [login] not defined.`

**Cause.** A guest opened the connect route, and the `auth` middleware wants to redirect to a route named `login` that your application does not have.

**Fix.** Give your login route the name `login`, or use the middleware of the guard your application does use; see [Who may connect](connecting.md#who-may-connect).

### 403 on `/linkedin/connect`

**Cause.** You added an ability such as `can:manage-linkedin` and the signed-in user fails it, or the gate is not defined. An undefined gate denies everybody.

**Fix.** Define the gate in `AppServiceProvider::boot()`; see the [quick start](quickstart.md#1-allow-only-administrators-to-connect).

### 404 on `/linkedin/connect`

**Cause.** `LINKEDIN_ROUTES_ENABLED` is `false`, `LINKEDIN_ROUTE_PREFIX` changed the address, or the route cache is out of date.

**Fix.** Run `php artisan route:list --name=linkedin` to see the real addresses, and `php artisan route:clear`.

### `Session store not set on request.`

**Cause.** `routes.middleware` in your published `config/linkedin.php` is `null` or an empty array. That removes every middleware, including `web`, and the routes need the session.

**Fix.** Set it to `['web', 'auth']`, plus your own ability.

### Every visitor can still open the connect route after upgrading to 1.8

**Cause.** A `config/linkedin.php` that was published before 1.8 still says `'middleware' => ['web']`. A published config wins over the package default.

**Fix.** Change it to `['web', 'auth']` or stricter.

## Flash messages after connecting

### `Configure LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET first.`

**Cause.** One of the two values is empty, or the config was cached before you filled them in.

**Fix.** Fill in both in `.env`, then `php artisan config:clear`.

### `LinkedIn connection denied: ...`

**Cause.** LinkedIn sent the user back with an error instead of a code. Either the member declined, or the request asked for a scope your LinkedIn app may not use. In the second case the message continues with `Your LinkedIn app is missing the "Community Management API" product, which company pages require. Request it, or connect with your personal profile only.`

**Fix.** Request the product, or connect with `route('linkedin.connect', ['profile_only' => 1])`. When the message names `openid` or `profile`, add the **Sign In with LinkedIn using OpenID Connect** product to the LinkedIn app; for `w_member_social`, add **Share on LinkedIn**.

### `Invalid or expired connection session. Please try again.`

**Cause.** The callback got no `code`, or the `state` in the address does not match the one in the session. The session cookie did not survive the trip to LinkedIn and back: the flow started on another host (`example.com` against `www.example.com`), the session expired, or the callback was opened twice.

**Fix.** Start again from the connect route, on the same host as the redirect URL in the LinkedIn app.

### `Connecting failed: LinkedIn did not accept the authorization. Please try again.`

**Cause.** LinkedIn refused to exchange the code for a token. The log has the reason: look for `LinkedIn returned an error while fetching the token:` followed by LinkedIn's answer. Two things to check: the redirect URL in the LinkedIn app must equal the address of the callback route exactly, and `LINKEDIN_CLIENT_SECRET` must be the current secret.

**Fix.** Correct what the log names, then connect again. See [the redirect URI](installation.md#the-redirect-uri).

### `Connecting failed: the LinkedIn profile could not be fetched. Please try again.`

**Cause.** The token was issued, but the call that reads the member's name and id failed. The log has `Could not fetch the LinkedIn profile:` followed by LinkedIn's answer, or `LinkedIn returned a profile without a member id`.

**Fix.** Check that the LinkedIn app has the **Sign In with LinkedIn using OpenID Connect** product, then connect again.

### `Connecting failed: LinkedIn could not be reached. Please try again.`

**Cause.** No answer came back: a timeout, a DNS failure or a refused connection.

**Fix.** Try again. When it keeps happening, check whether your server can reach `www.linkedin.com` and `api.linkedin.com`.

### `Connecting failed because of an unexpected error. Please try again.`

**Cause.** Something outside the package failed while the connection was stored. The log has the real exception. The usual ones:

| In the log | Cause | Fix |
| --- | --- | --- |
| A database error that names the table `linkedin_accounts` | The migration did not run | `php artisan migrate` |
| `No application encryption key has been specified.` | `APP_KEY` is empty; the tokens are stored encrypted | `php artisan key:generate` |

## Publishing

### `No active LinkedIn connection.`

**Exception.** `LinkedInNotConnected`.

**Cause.** Nobody connected an account yet, or `LinkedIn::disconnect()` removed it.

**Fix.** Send an administrator to the connect route.

### `The LinkedIn connection has expired. Please reconnect the account.`

**Exception.** `LinkedInConnectionExpired`.

**Cause.** The access token expired, and there is no refresh token or the refresh token expired too.

**Fix.** Connect again. This is the one failure a user of your application can solve.

### `The LinkedIn connection was not granted the "w_organization_social" scope ...`

**Exception.** `LinkedInScopeMissing`. The full message is `The LinkedIn connection was not granted the "w_organization_social" scope (which requires the "Community Management API"). Reconnect the account after adding the product to your LinkedIn app.` The same sentence exists for `r_organization_admin`.

**Cause.** The stored token does not carry the scope. You set `LINKEDIN_ORGANIZATION_URN` or `LINKEDIN_ORGANIZATIONS_ENABLED` after connecting, or you connected with `profile_only=1`. No request was sent.

**Fix.** Make sure the LinkedIn app has the Community Management API, then connect again. Do not retry and do not edit the `scopes` column; a token never gains scopes.

### `No LinkedIn company page given, and none configured (linkedin.organization_urn).`

**Exception.** `LinkedInConfigurationException`.

**Cause.** `postAsOrganization()` was called without a URN, and `LINKEDIN_ORGANIZATION_URN` is empty or the config is cached.

**Fix.** Pass the URN as the second argument, or set the env value and run `php artisan config:clear`.

### `LinkedIn rejected the post: ...`

**Exception.** `LinkedInApiException` with operation `publish`. `$e->status` is the HTTP status and `$e->body` is LinkedIn's answer.

| Status | Cause | Fix |
| --- | --- | --- |
| 401 | LinkedIn no longer accepts the token, for example because the member revoked the app | Connect again |
| 403 | The token lacks the scope (a connection from before 1.4 has no recorded scopes, so the package could not tell), or the member has no role on that company page that may post | Connect again, or fix the role on the page |
| Another 4xx on every call, from one day to the next | `LINKEDIN_API_VERSION` names a version LinkedIn has retired | Set a current version from [LinkedIn's versioning page](https://learn.microsoft.com/linkedin/marketing/versioning) |
| 5xx | A failure at LinkedIn | Retry later |

### `LinkedIn could not be reached (publish)`

**Exception.** `LinkedInApiException` with status `0`; `$e->isConnectionProblem()` is true. The word between brackets is the operation: `token`, `profile`, `publish`, `organizations` or `image`.

**Cause.** No answer came back at all.

**Fix.** Retry. In a queued job, rethrow the exception so the queue tries again; see the [quick start](quickstart.md#3-publish-from-a-queued-job).

### The post went out, but `urn` and `permalink` are empty

**Cause.** LinkedIn answered with a success status but without the `x-restli-id` header. The package returns `['urn' => '', 'permalink' => '']` and throws nothing.

**Fix.** Check for an empty `urn` before you store the result. In a test, fake the header; see [Testing](testing.md).

### Hashtags and mentions show up as plain text

**Cause.** The package escapes every reserved character in the text, including `#` and `@`.

**Fix.** None within the package; see [What the text may contain](publishing.md#what-the-text-may-contain).

### The application posts as the wrong account

**Cause.** The package keeps one connection. `LinkedIn::account()` is the account that was connected last, and rows of earlier connects stay in the table.

**Fix.** Check `LinkedIn::account()?->name`. Call `LinkedIn::disconnect()` and connect the right account.

### `The payload is invalid.` or `The MAC is invalid.`

**Exception.** Laravel's `Illuminate\Contracts\Encryption\DecryptException`.

**Cause.** `APP_KEY` changed after the account was connected. The tokens were encrypted with the old key.

**Fix.** Call `LinkedIn::disconnect()` and connect again.

## Images and company pages

### `Could not initialize the LinkedIn image upload: ...`, `LinkedIn returned no upload URL for the image` or `LinkedIn refused the image upload: ...`

**Exception.** `LinkedInApiException` with operation `image`.

**Cause.** One of the two upload calls failed. `$e->status` and `$e->body` have LinkedIn's answer. The owner URN you passed must be the author you post as.

**Fix.** Upload with the URN of the author: the member URN for `postAsMember()`, the company page URN for `postAsOrganization()`.

### `Could not fetch the LinkedIn company pages: ...`

**Exception.** `LinkedInApiException` with operation `organizations`.

**Cause.** LinkedIn refused the list. With status 403 the token has no `r_organization_admin`.

**Fix.** Follow the three steps on [Company pages](company-pages.md), in that order.

### `LinkedIn::canListOrganizations()` stays false

**Cause.** It needs both `LINKEDIN_ORGANIZATIONS_ENABLED=true` and a token with `r_organization_admin`.

**Fix.** Turn the setting on, run `php artisan config:clear`, and connect again.

### A new company page is missing from the list

**Cause.** The list is cached for `organizations.cache_ttl` seconds, one hour by default.

**Fix.** `LinkedIn::organizations(fresh: true)` or `LinkedIn::forgetOrganizations()`.
