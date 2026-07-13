# Changelog

> 🇬🇧 [English changelog](../../CHANGELOG.md)

Alle noemenswaardige wijzigingen aan `darvis/api-linkedin` worden hier bijgehouden.

## [1.1.0] - 2026-07-13

### Gewijzigd

- **De code is nu volledig Engelstalig**: comments, docblocks, exception-berichten en testomschrijvingen.
- **De flash-berichten van de ingebouwde OAuth-routes zijn nu Engels** (bijv. `LinkedIn connected as ...` i.p.v. `LinkedIn gekoppeld als ...`). Dit is zichtbaar voor eindgebruikers. Applicaties die Nederlandse meldingen willen, kunnen `session('linkedin_status')` / `session('linkedin_error')` uitlezen en zelf vertalen.

### Toegevoegd

- Tweetalige documentatie: Engels in de root, Nederlands onder [`docs/nl/`](README.md).

## [1.0.0] - 2026-07-13

### Toegevoegd

- OAuth 2.0 (authorization code) flow met LinkedIn, zonder externe dependency (`LinkedInOAuth`).
- Berichten plaatsen via de Posts API namens een lid of bedrijfspagina (`LinkedInPublisher`).
- `LinkedInAccount`-model met versleutelde tokens en automatische token-refresh.
- `LinkedIn`-facade en `LinkedInManager` voor `postAsMember()` / `postAsOrganization()`.
- Optionele, configureerbare connect/callback-routes en controller.
- Publiceerbare config en migratie.
