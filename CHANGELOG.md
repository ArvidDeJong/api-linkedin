# Changelog

All notable changes to `darvis/api-linkedin` are documented here.

## [Unreleased]

### Added

- Laravel Boost skill `api-linkedin-development` in `resources/boost/skills/`. It covers how a publish runs, what every failure gives you (exception type, message, whether a request went out), publishing from a job, company pages, article cards with an uploaded image, the connect flow and its flash messages, the pitfalls in a host app (the open connect route, one global connection, unknown scopes, tokens that never gain scopes), the settings and how to test with `Http::fake()`.
- A social preview image for the documentation site (`docs/assets/images/social-preview.png`), set as the default `image` for every page in `docs/_config.yml`.

## [1.7.0] - 2026-09-20

### Added

- `LinkedInConfig` with named accessors is the one place that reads the package config. Every
  default is written down once, so a caller cannot quietly disagree with `config/linkedin.php`
  about what it is. Twenty-three reads spread over nine files now go through it, including
  `routes/web.php`. The controller's private `key()` helper is gone: the four session keys have
  their own accessors.

### Changed

- The config keys are in alphabetical order, both the groups and the keys inside them. No key,
  default or behaviour changed.

## [1.6.1] - 2026-09-18

The release that 1.6.0 was meant to be. The package now has the same shape as
the other darvis packages: a documentation site, a Laravel Boost guideline, and
Pint, Larastan and the shared CI matrix behind it. Nothing changes in the public
API.

### Added

- Laravel Boost guideline in `resources/boost/guidelines/core.blade.php`, so host apps that run Boost get the package's rules (token beats config, typed exceptions, one global connection, article cards) in their AI context.
- Pint (`composer lint`, `composer format`) and Larastan level 8 (`composer analyse`) as development tooling, and the shared CI workflow that runs the suite on PHP 8.2 to 8.4 with Laravel 11, 12 and 13 on the lowest and the latest dependencies.
- `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md`, issue templates and a Dependabot schedule for the dev tooling.

### Changed

- The documentation moved to a GitHub Pages site at https://arviddejong.github.io/api-linkedin/, built from `docs/`. The README now holds the quick start and links there.
- Orchestra Testbench 11 is allowed for the test suite. Nothing changes for host apps.

### Removed

- The Dutch mirror of the README and changelog under `docs/nl/`. The documentation is English only, like the code.

## [1.6.0] - 2026-09-18

Withdrawn. The tag was created before the release branch was merged, so it points
at the 1.5.0 code. Packagist hides the version; install 1.6.1 instead.

## [1.5.0] - 2026-07-13

Bring your own image. Until now a shared link got whatever preview LinkedIn could
scrape from the page's Open Graph tags — which needs LinkedIn to be able to reach
the page, is cached per URL, and gives you no say over the picture.

### Added

- `Article` — a link card to attach to a post: `source`, `title`, `description` and a `thumbnail` you supply. The card stays clickable through to your site, unlike an image post, where the link survives only as plain text in the commentary.
- `LinkedIn::uploadImage($ownerUrn, $contents, $contentType)` and the `LinkedInImages` service. Takes the raw bytes, not a path: the package does no filesystem work, so the image may come from disk, S3 or anywhere else. The owner must be the author the post is published as — LinkedIn refuses an image owned by anyone else. **No new scope**: uploading rides on the `w_member_social` / `w_organization_social` the post already needs, so no reconnect is required.
- `postAsMember()`, `postAsOrganization()` and `publish()` take an optional `Article`.
- `LinkedInApiException::OPERATION_IMAGE` for failures during an upload.

### Changed

- Nothing. Omit the `Article` and the payload is byte-for-byte what it was, so LinkedIn keeps building the preview from the Open Graph tags exactly as before.

## [1.4.0] - 2026-07-13

The connection now knows what it may actually do. Until this release the package
decided that from the **config**, while LinkedIn decides it from the **granted
scopes** — and the two drift apart the moment a LinkedIn app misses a product. That
gap made an app without the Community Management API impossible to connect at all,
and made every company page it did offer publish into an unexplained 403.

### Added

- `Scopes` — the scopes the package works with, grouped by the LinkedIn product that grants them. `Scopes::MEMBER` is the set every app can hold.
- Scope knowledge on `LinkedInAccount`: `grantedScopes()`, `knowsScopes()`, `hasScope()`, `lacksScope()`, `canPostAsOrganization()`, `canListOrganizations()`. `hasScope()` and `lacksScope()` are deliberately **not** each other's negation — both are `false` when the scopes are unknown, so the package never guesses.
- `LinkedIn::canListOrganizations()` and `LinkedIn::canPostAsOrganization()` — gate your UI on these instead of on the config, or you offer targets that cannot be published to.
- `LinkedInOAuth::authorizationUrl($state, $scopes)` and `connectFromCode($code, $requestedScopes)` take the scopes explicitly. Without this the only way to narrow a request was to mutate the config mid-request.
- `?profile_only=1` on the built-in connect route asks for `Scopes::MEMBER` only, so an app without the Community Management API can still connect — on the member's profile. LinkedIn refuses the *entire* authorization over one unauthorized scope, so asking for less is the only way through.
- `AuthorizationDenial::fromCallback($request)` reads a refused authorization: `description` (HTML-decoded — LinkedIn escapes it, and echoing it straight into Blade showed the entities), `isScopeProblem()`, `missingScope()` and `isRecoverableWithMemberScopes()`.
- `LinkedInScopeMissing` — thrown *before* the request goes out when the stored scopes prove the call would come back as a 403. Carries the `scope` and names the missing LinkedIn product.
- `linkedin.session.scopes_key` — where the built-in flow remembers the scopes it asked for.

### Changed

- `organizations()` and publishing as a company page now refuse up front when the token provably lacks the scope, instead of firing a request that returns a 403 that is indistinguishable from an expired token or a page you do not administer.
- `connectFromCode()` no longer records the *configured* scopes when LinkedIn omits `scope` from the token response. It records the scopes actually requested for that flow; the config may have changed since the redirect, and a wrong guess is worse than none — every capability check leans on this column.

### Upgrading

Nothing breaks. A connection stored before 1.4 has no recorded scopes: `grantedScopes()` returns `null`, both `hasScope()` and `lacksScope()` return `false`, and no guard fires — such an account keeps working exactly as before. Reconnect it to unlock the capability checks.

## [1.3.0] - 2026-07-13

### Added

- Typed exceptions, so callers can react on the **type** instead of parsing the message (which is free to change between releases). All of them extend `LinkedInException`, so existing `catch (LinkedInException $e)` keeps working:
  - `LinkedInNotConnected` — no account is connected.
  - `LinkedInConnectionExpired` — the token expired and cannot be refreshed; the user must reconnect. The one failure an end user can act on.
  - `LinkedInConfigurationException` — a required setting is missing (e.g. posting to a company page without an URN).
  - `LinkedInApiException` — LinkedIn answered with an error. Carries `operation` (`token`, `profile`, `publish`, `organizations`), `status`, `body` and `isAuthorizationProblem()` (401/403).

Applications that show messages in another language can now map these types onto their own copy, instead of displaying the package's English text.

## [1.2.0] - 2026-07-13

### Added

- `LinkedIn::organizations()` lists the company pages the connected member administers (name, URN, vanity name), through the `organizationAcls` endpoint. The list is cached (`linkedin.organizations.cache_ttl`, one hour by default); `organizations(fresh: true)` and `forgetOrganizations()` bypass or clear it.
- `linkedin.organizations.enabled` — off by default. Turning it on adds the `r_organization_admin` scope (and `w_organization_social`) to the authorization request. **Requires reconnecting**: tokens issued earlier do not carry the scope.
- New service `LinkedInOrganizations`, registered as a singleton.

### Changed

- `postAsOrganization()` takes an optional second argument: the URN of the page to post on. Without it the default from `linkedin.organization_urn` is used, so existing calls keep working.

## [1.1.0] - 2026-07-13

### Changed

- **The code is now fully English**: comments, docblocks, exception messages and test descriptions.
- **The flash messages of the built-in OAuth routes are now English** (e.g. `LinkedIn connected as ...` instead of `LinkedIn gekoppeld als ...`). This is visible to end users. Applications that want Dutch messages can read `session('linkedin_status')` / `session('linkedin_error')` and translate them themselves.

### Added

- Bilingual documentation: English in the root, Dutch under `docs/nl/` (the Dutch mirror was removed again in a later release).

## [1.0.0] - 2026-07-13

### Added

- OAuth 2.0 (authorization code) flow with LinkedIn, without an external dependency (`LinkedInOAuth`).
- Publishing posts through the Posts API on behalf of a member or a company page (`LinkedInPublisher`).
- `LinkedInAccount` model with encrypted tokens and automatic token refresh.
- `LinkedIn` facade and `LinkedInManager` for `postAsMember()` / `postAsOrganization()`.
- Optional, configurable connect/callback routes and controller.
- Publishable config and migration.
