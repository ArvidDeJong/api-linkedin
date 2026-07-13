# Changelog

Alle noemenswaardige wijzigingen aan `darvis/api-linkedin` worden hier bijgehouden.

## [1.0.0] - 2026-07-13

### Toegevoegd

- OAuth 2.0 (authorization code) flow met LinkedIn, zonder externe dependency (`LinkedInOAuth`).
- Berichten plaatsen via de Posts API namens een lid of bedrijfspagina (`LinkedInPublisher`).
- `LinkedInAccount`-model met versleutelde tokens en automatische token-refresh.
- `LinkedIn`-facade en `LinkedInManager` voor `postAsMember()` / `postAsOrganization()`.
- Optionele, configureerbare connect/callback-routes en controller.
- Publiceerbare config en migratie.
