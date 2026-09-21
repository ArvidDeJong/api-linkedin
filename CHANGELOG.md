# Changelog

All notable changes to `darvis/api-linkedin` are documented here.

## [Unreleased]

## [1.8.1] - 2026-09-21

Documentation only; nothing in the package changes.

### Fixed

- **The required LinkedIn products were incomplete.** The docs, the README and the FAQ named only "Share on LinkedIn" for a profile connection. The package always requests `openid` and `profile` as well, and LinkedIn grants those through the product "Sign In with LinkedIn using OpenID Connect". An app with only "Share on LinkedIn" is refused, so the requirements now name both products.
- **`LinkedIn::organizations()` does not require `linkedin.organizations.enabled`.** The company pages documentation said it did. The method looks at the token only; the setting decides which scopes the next connect asks for, and it is one of the two conditions of `LinkedIn::canListOrganizations()`.
- **A missing `w_organization_social` scope is not a 403.** The error documentation explained a 403 on a company page post with a missing scope. With known scopes the package throws `LinkedInScopeMissing` before any request; a 403 remains for a connection from before 1.4 (unknown scopes) and for a member without a role on the page.
- **The "wiring the flow yourself" example called `connectFromCode()` without the requested scopes.** The granted scopes were then guessed from the config when LinkedIn left `scope` out of the token response. The example now passes the same set to `authorizationUrl()` and `connectFromCode()`, checks the `state`, protects its own routes and handles a refused authorization. The package `CLAUDE.md` claimed that this fallback to the config does not exist; it does, when the second argument is `null`.
- **"Automatic token refresh" was promised unconditionally.** A token is only renewed when LinkedIn issued a refresh token for your app; otherwise the account has to be connected again after the access token expires.
- The Boost guideline told host apps to read settings "through `LinkedInManager`". The manager answers three questions (`isConfigured()`, `organizationEnabled()`, `organizationListingEnabled()`); every other setting is on `LinkedInConfig`.
- The Boost skill's callback test example did not sign in, so it failed against the `auth` default of 1.8.
- Stated what the docs left out: a 2xx answer without an `x-restli-id` header returns an empty `urn` and `permalink` without an exception; `'middleware' => null` or `[]` in a published config removes every middleware, including `web`; hashtags and mentions arrive as plain text because every reserved character is escaped; `LinkedIn::account()` can be `null`.
- Removed claims about LinkedIn that the package cannot vouch for (how previews are cached, reuse of an image URN across authors, image posts), and linked LinkedIn's own pages for API versions, refresh tokens and reserved characters instead.
- The config table lists every `session.*` key with its default, and `routes.middleware` with what `null` does.

### Added

- Documentation pages: **Quick start** (one complete example: gate, settings page with the flash messages, queued job), **Troubleshooting** (every flash message and exception message quoted literally, with cause and fix) and **Testing** (a complete test, the addresses to fake, failures and the connect flow). "Check that it works" in the installation page.
- README sections `Requirements`, `Who may connect`, `Quick start`, `Changelog`, `Contributing` and `Security`, in the same order as the other darvis packages.
- `tests/DocsSiteTest.php` guards that the home page links to every page, that the troubleshooting page quotes messages that exist in `src/`, that the requirements agree with `composer.json`, and the README section order.

### Removed

- `docs/README.md`. It duplicated the site index and was excluded from the site; the index of the documentation is `docs/index.md`.
- The "Author" section of the README; the license names the author.

## [1.8.0] - 2026-09-21

### Security

- **The connect and callback routes required no login.** Whoever completes the connect flow becomes the one connection your whole application posts with, and with the package defaults any visitor could open `/linkedin/connect` and do that. The default `routes.middleware` is now `['web', 'auth']`: a guest is redirected to your `login` route (or gets a 401 on a JSON request). **What you have to do:** a `config/linkedin.php` you published earlier keeps its own value and still says `['web']`, so open that file and change it yourself. Signed in is usually still too wide; add an ability so only the right people can connect:

  ```php
  // config/linkedin.php
  'routes' => [
      // ...
      'middleware' => ['web', 'auth', 'can:manage-linkedin'],
  ],

  // app/Providers/AppServiceProvider.php, in boot()
  Gate::define('manage-linkedin', fn (User $user) => $user->is_admin);
  ```

  If you never published the config, the new default applies right away. Does your application have no `login` route, or do the people who connect the account sign in through another guard? Then set the middleware that fits, for example `['web', 'auth:admin']`. If you protected the routes in another way and really want the old behaviour, set `'middleware' => ['web']` in the published config. After upgrading, check who is connected (`LinkedIn::account()?->name`) and reconnect if it is not the account you expect.
- **A failed connect showed internal details to the user.** The callback flashed `Connecting failed: ` followed by the raw exception message: LinkedIn's response body, or for an unexpected error things like a database or decryption message. The flash message under `linkedin_error` is now a fixed sentence (`Connecting failed: LinkedIn did not accept the authorization. Please try again.`, `... the LinkedIn profile could not be fetched ...`, `... LinkedIn could not be reached ...`, or `Connecting failed because of an unexpected error. Please try again.`). The exception still goes to `report()`, so the details are in your log. **What you have to do:** nothing, unless your application matched on the old text; look in the log for the cause instead. If you wrote your own callback, don't echo `$e->getMessage()` there either.
- **Reconnecting an account did not make it the current one.** "Current" was the row with the highest id. A member who connected again kept their old row, so when another account had been connected in between, the application kept posting as that other account while the screen said `LinkedIn connected as <name>`. `LinkedInAccount::current()` now returns the account that was connected last (`updated_at`, then `id`); a reconnect always counts, and a token refresh does not. Nothing changes on upgrade for an application with one stored account. **What you have to do:** if more than one member ever connected, check `LinkedIn::account()?->name` after upgrading, and call `LinkedIn::disconnect()` before reconnecting when in doubt. Don't `touch()` or update rows of the accounts table yourself: that now makes a row current.
- **The cached list of company pages outlived the connection.** `LinkedIn::disconnect()` left the cached page list behind, and `forgetOrganizations()` could not clear it afterwards because it needs a connected account; a reconnect kept serving the list of the previous token for up to `organizations.cache_ttl` seconds. Both `disconnect()` and a reconnect now drop the cached list. **What you have to do:** nothing.
- **A timeout escaped the package's exceptions.** The docs promise that every failure is a `LinkedInException`, but an unreachable LinkedIn threw Laravel's `Illuminate\Http\Client\ConnectionException`, so a `catch (LinkedInException)` missed it and the error page or log could show the failing URL, which for an image upload is a signed, single-use address. Every call now throws a `LinkedInApiException` with `status` 0, an empty `body`, the new `isConnectionProblem()` returning true and the original exception as `getPrevious()`. A successful (2xx) token or profile response with an empty, non JSON or incomplete body now throws a `LinkedInApiException` too, instead of a `TypeError`. **What you have to do:** if you caught `ConnectionException` around a package call, catch `LinkedInApiException` and ask `$e->isConnectionProblem()` instead. If a queued job treats `$e->status < 500` as final, check `isConnectionProblem()` first so a timeout is still retried:

  ```php
  } catch (LinkedInApiException $e) {
      if ($e->isConnectionProblem()) {
          throw $e; // no answer at all: let the queue retry
      }
      // ...
  }
  ```

### Added

- `LinkedInApiException::isConnectionProblem()` and `LinkedInApiException::unreachable()`. The constructor takes an optional `$previous` as its last argument.

### Changed

- The default of `linkedin.routes.middleware` is `['web', 'auth']` instead of `['web']`, in `config/linkedin.php` and in `LinkedInConfig::routeMiddleware()`. See Security above for what to check in a published config.
- `LinkedInAccount::current()` orders on `updated_at` and then `id`, instead of `id` alone.
- A token refresh no longer changes `updated_at` of the account; that column now says when the account was connected.
- `LinkedIn::disconnect()` and a reconnect clear the cached company pages.
- The `linkedin_error` flash message after a failed code exchange is a fixed sentence instead of the exception message.
- An unreachable LinkedIn throws `LinkedInApiException` (status 0) instead of Laravel's `ConnectionException`.

## [1.7.1] - 2026-09-21

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
