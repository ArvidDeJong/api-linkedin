# Changelog

> 🇳🇱 [Nederlandse changelog](docs/nl/CHANGELOG.md)

All notable changes to `darvis/api-linkedin` are documented here.

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

- Bilingual documentation: English in the root, Dutch under [`docs/nl/`](docs/nl/README.md).

## [1.0.0] - 2026-07-13

### Added

- OAuth 2.0 (authorization code) flow with LinkedIn, without an external dependency (`LinkedInOAuth`).
- Publishing posts through the Posts API on behalf of a member or a company page (`LinkedInPublisher`).
- `LinkedInAccount` model with encrypted tokens and automatic token refresh.
- `LinkedIn` facade and `LinkedInManager` for `postAsMember()` / `postAsOrganization()`.
- Optional, configurable connect/callback routes and controller.
- Publishable config and migration.
