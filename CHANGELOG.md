# Changelog

> 🇳🇱 [Nederlandse changelog](docs/nl/CHANGELOG.md)

All notable changes to `darvis/api-linkedin` are documented here.

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
