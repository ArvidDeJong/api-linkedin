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

### The config says what to ask for; the token says what you got

This is the axis the whole package turns on. `config('linkedin.*')` decides which scopes are *requested*; `$account->scopes` records what LinkedIn actually *granted*. They drift apart the moment a LinkedIn app misses a product, and **the token always wins**. Every capability question must therefore be asked of the account (`hasScope()`, `canListOrganizations()`, `canPostAsOrganization()`), never of the config. Gate a UI on the config and you offer targets that publish into a 403.

Two rules keep this honest, and both are easy to undo by accident:

- **Never guess the granted scopes.** `connectFromCode()` records `$token['scope']`, falling back only to the scopes *that flow* requested. It must never fall back to `config`, which may have changed since the redirect. A wrong value here silently corrupts every check downstream.
- **`hasScope()` and `lacksScope()` are not each other's negation.** Both are `false` when the scope set is unknown (an account from before 1.4). Offer features on `hasScope()` — promise only what you know. Refuse calls on `lacksScope()` — block only what you know will fail. Collapsing them into one method breaks either old accounts or the guards.

### Listing pages is opt-in, and the scope is the reason

`LinkedIn::organizations()` needs `r_organization_admin`, which is only requested when `config('linkedin.organizations.enabled')` is on. It is off by default on purpose: LinkedIn refuses the *entire* authorization over a single unauthorized scope, so an app that only holds "Share on LinkedIn" could not connect at all if the scope were always requested. That is also why `?profile_only=1` exists — asking for less is the only way through for an app without the Community Management API.

**Enabling the config does not upgrade an existing token.** A token minted before the flag was flipped has no `r_organization_admin`. Since 1.4 that no longer surfaces as a mystery 403: `organizations()` throws `LinkedInScopeMissing` before the request goes out. Do not "fix" a scope failure by retrying — a token never gains scopes; the account must be reconnected.

The `organizationAcls` response decorates each ACL through a Rest.li projection; the organization object arrives under the tilde-suffixed key `organization~`. If the projection is ignored, that key is absent — `fetch()` falls back to the bare URN as the name, so callers always get a usable list.

The list is cached per account (`linkedin.organizations.cache_ttl`, 0 disables). Anything that invalidates page membership should call `forgetOrganizations()`.

### Exceptions are typed; never match on the message

Every failure throws a subclass of `LinkedInException`: `LinkedInNotConnected`, `LinkedInConnectionExpired`, `LinkedInConfigurationException`, `LinkedInScopeMissing` (carries the `scope`) and `LinkedInApiException` (which carries `operation`, `status`, `body`, `isAuthorizationProblem()`).

The one place a message *is* parsed is `AuthorizationDenial` — but that is LinkedIn's wire format on the OAuth callback, not our own text, and it is deliberately confined to that single class so host apps never string-match on `'scope'` themselves. It also HTML-decodes the description, which LinkedIn escapes (`&quot;`) and Blade would then escape a second time.

This exists because the messages are English and **have already changed once** (1.1.0 translated the whole package). Host apps that show messages in another language must map on the *type*. So: when you add a throw site, give it the right type and add the operation constant — do not lean on the wording, and do not "helpfully" merge types back into a bare `LinkedInException`.

`LinkedInConnectionExpired` is the one an end user can act on (reconnect); everything else is either a developer error or an upstream failure. Keep that distinction intact, UIs branch on it.

### One global connection, not one per user

`LinkedInAccount::current()` is simply `latest('id')->first()` — the package assumes **a single active connection for the whole application**, not an account per logged-in user. `disconnect()` truncates the table. Anyone wanting multi-tenant / per-user connections must change the retrieval path (`current()`, `LinkedInManager::requireAccount()`, `disconnect()`), not just add a column.

### Posting as the company page reuses the member's token

There is no separate organization token. `postAsOrganization()` uses the access token of the connected member and only sets a different `author` URN (from `config('linkedin.organization_urn')`). That only works if the token carries the `w_organization_social` scope and the member administers the page.

The consequence that easily bites: **`scopes()` is derived from config.** Fill in `organization_urn` *after* connecting and the existing token still has no `w_organization_social`. `LinkedInPublisher` guards on this — it refuses an `urn:li:organization:` author when the token provably lacks the scope — but the underlying coupling between config and token state remains, and reconnecting is the only cure.

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
