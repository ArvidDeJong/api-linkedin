# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`darvis/api-linkedin` — a standalone Laravel package (not an app) that lets a Laravel project publish posts on LinkedIn: on the personal profile and on a company page, through OAuth 2.0 and the Posts API. Tested against Laravel 11/12/13 on PHP 8.2+.

## Language convention

**Code is English, documentation is bilingual.**

- Variables, function names, comments, docblocks, exception messages, flash/UI messages and Pest test descriptions: **English**.
- Documentation: **English in the root** (`README.md`, `CHANGELOG.md`), **Dutch under `docs/nl/`** (`docs/nl/README.md`, `docs/nl/CHANGELOG.md`). The two versions are linked to each other at the top and must be kept in sync — a change to one means a change to the other.

**No external HTTP dependency, by design.** Everything goes through `Illuminate\Support\Facades\Http`. Do not add a Guzzle wrapper, a Socialite provider or a LinkedIn SDK.

## Commands

```bash
composer install
composer test                                    # pest, full suite

vendor/bin/pest tests/LinkedInOAuthTest.php      # one file
vendor/bin/pest --filter="refreshes an expired token"   # one test
```

No linter or formatter is configured in this package.

## Releasing

The package is published on Packagist as `darvis/api-linkedin`; a GitHub webhook pushes every tag on `ArvidDeJong/api-linkedin` to Packagist automatically. A release is therefore just an annotated tag on `main`:

```bash
git tag -a v1.2.0 -m "v1.2.0 — ..." && git push origin main --follow-tags
```

**Never add a `version` field to composer.json.** Packagist derives the version from the tag and *skips* any tag whose number does not match a hardcoded `version` (`Skipped tag v1.1.0, tag (1.1.0.0) does not match version (1.0.0.0) in composer.json`). That silently produced a release that never landed. The field was removed for this reason; keep it out.

Update both changelogs ([CHANGELOG.md](CHANGELOG.md) and [docs/nl/CHANGELOG.md](docs/nl/CHANGELOG.md)) in the same commit as the release.

## Architecture

The chain is `LinkedIn facade` → `LinkedInManager` → (`LinkedInOAuth` | `LinkedInPublisher` | `LinkedInOrganizations`) → `LinkedInAccount`. All services are singletons; `LinkedInManager` is aliased as `'linkedin'` in the container.

- [LinkedInManager.php](src/LinkedInManager.php) — ergonomic front (`postAsMember()`, `postAsOrganization()`, `publish()`, `organizations()`). Contains no HTTP logic; resolves the account and delegates.
- [Services/LinkedInOAuth.php](src/Services/LinkedInOAuth.php) — authorization URL, code exchange, profile fetch, token refresh.
- [Services/LinkedInPublisher.php](src/Services/LinkedInPublisher.php) — `POST /rest/posts`.
- [Services/LinkedInOrganizations.php](src/Services/LinkedInOrganizations.php) — `GET /rest/organizationAcls`, the company pages the member administers.
- [Models/LinkedInAccount.php](src/Models/LinkedInAccount.php) — the stored connection.

### Listing pages is opt-in, and the scope is the reason

`LinkedIn::organizations()` needs `r_organization_admin`, which is only requested when `config('linkedin.organizations.enabled')` is on. It is off by default on purpose: apps that only hold "Share on LinkedIn" would get an OAuth error if the scope were always requested.

Same trap as `organization_urn` below: **enabling the config does not upgrade an existing token.** A token minted before the flag was flipped has no `r_organization_admin`, so `organizations()` returns a 403 until the account is reconnected. When something 403s here, suspect the token's scope set, not the request.

The `organizationAcls` response decorates each ACL through a Rest.li projection; the organization object arrives under the tilde-suffixed key `organization~`. If the projection is ignored, that key is absent — `fetch()` falls back to the bare URN as the name, so callers always get a usable list.

The list is cached per account (`linkedin.organizations.cache_ttl`, 0 disables). Anything that invalidates page membership should call `forgetOrganizations()`.

### One global connection, not one per user

`LinkedInAccount::current()` is simply `latest('id')->first()` — the package assumes **a single active connection for the whole application**, not an account per logged-in user. `disconnect()` truncates the table. Anyone wanting multi-tenant / per-user connections must change the retrieval path (`current()`, `LinkedInManager::requireAccount()`, `disconnect()`), not just add a column.

### Posting as the company page reuses the member's token

There is no separate organization token. `postAsOrganization()` uses the access token of the connected member and only sets a different `author` URN (from `config('linkedin.organization_urn')`). That only works if the token carries the `w_organization_social` scope and the member administers the page.

The consequence that easily bites: **`scopes()` is derived dynamically from `organization_urn`.** If that config is filled in *after* connecting, the existing token does not carry `w_organization_social` and publishing fails — reconnecting is then required. A non-obvious coupling between config and token state.

### Token lifecycle

`LinkedInPublisher::publish()` always calls `LinkedInOAuth::freshAccessToken()` first. That refreshes automatically on an expired token (with a one-minute margin, see `tokenHasExpired()`) and throws a `LinkedInException` when no usable refresh token is left. New API calls should go through this path; never read `$account->access_token` directly.

Tokens are `encrypted` casts, so the application needs an `APP_KEY` and the columns are `text`.

### The redirect URI is derived, not configured

`LinkedInOAuth::redirectUri()` is `route(config('linkedin.routes.callback_name'))`. There is no `LINKEDIN_REDIRECT_URI` env var; the callback route determines the URI, and it must match the redirect URL in the LinkedIn app exactly. If you set `routes.enabled` to `false` to wire the flow yourself, your own route must carry the name from `routes.callback_name` — otherwise the `redirect_uri` in both OAuth calls will not match.

The built-in routes are registered in `LinkedInServiceProvider::registerRoutes()` with the prefix/middleware from config; the route names themselves come from config inside [routes/web.php](routes/web.php).

### LinkedIn quirks encoded in the code

- **Commentary escaping** (`LinkedInPublisher::escapeCommentary()`): the Posts API reserves `| { } @ [ ] ( ) < > # * _ ~` and `\`. The backslash is replaced first, otherwise existing backslashes get double-escaped — keep that order intact.
- **The post URN comes from the `x-restli-id` response header**, not the body (the body is empty on a 201).
- **The `LinkedIn-Version` header** (`config('linkedin.api_version')`, format `YYYYMM`) is required on `/rest/*` and expires after roughly a year. A suddenly failing publish is often an expired version, not a bug.
- Link previews are not sent along: a URL in the text makes LinkedIn fetch the Open Graph tags itself.

### The table name is configurable

`config('linkedin.table')` is read by both `LinkedInAccount::getTable()` and the migration. Never hardcode the table name.

## Tests

Pest on top of Orchestra Testbench. [tests/Pest.php](tests/Pest.php) attaches `TestCase` + `RefreshDatabase` to *all* tests in `tests/`; [tests/TestCase.php](tests/TestCase.php) runs on in-memory SQLite and sets an `app.key` plus a valid LinkedIn config (including `organization_urn`) — a test that wants the behaviour *without* a company page must clear that config explicitly.

All LinkedIn calls are intercepted with `Http::fake()`; no traffic ever reaches LinkedIn. In a publish test, also fake the `x-restli-id` header, otherwise the URN comes back empty.
