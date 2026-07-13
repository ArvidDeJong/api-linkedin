# Changelog

> 🇬🇧 [English changelog](../../CHANGELOG.md)

Alle noemenswaardige wijzigingen aan `darvis/api-linkedin` worden hier bijgehouden.

## [1.2.0] - 2026-07-13

### Toegevoegd

- `LinkedIn::organizations()` geeft de bedrijfspagina's die het gekoppelde lid beheert (naam, URN, vanity name), via het `organizationAcls`-endpoint. De lijst wordt gecachet (`linkedin.organizations.cache_ttl`, standaard een uur); `organizations(fresh: true)` en `forgetOrganizations()` slaan de cache over of legen 'm.
- `linkedin.organizations.enabled` — standaard uit. Aanzetten voegt de scope `r_organization_admin` (en `w_organization_social`) toe aan de autorisatie. **Vereist opnieuw koppelen**: eerder uitgegeven tokens hebben die scope niet.
- Nieuwe service `LinkedInOrganizations`, geregistreerd als singleton.

### Gewijzigd

- `postAsOrganization()` accepteert een optioneel tweede argument: de URN van de pagina waarop je post. Zonder dat argument wordt de standaard uit `linkedin.organization_urn` gebruikt, dus bestaande aanroepen blijven werken.

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
