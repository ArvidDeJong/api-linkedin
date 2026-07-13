# Changelog

> 🇬🇧 [English changelog](../../CHANGELOG.md)

Alle noemenswaardige wijzigingen aan `darvis/api-linkedin` worden hier bijgehouden.

## [1.4.0] - 2026-07-13

De koppeling weet nu wat ze daadwerkelijk mag. Tot deze release leidde het package
dat af uit de **config**, terwijl LinkedIn het bepaalt met de **toegekende scopes** —
en die twee lopen uiteen zodra een LinkedIn-app een product mist. Door dat gat kon
een app zonder de Community Management API helemaal niet koppelen, en eindigde elke
bedrijfspagina die tóch werd aangeboden in een onverklaarde 403.

### Toegevoegd

- `Scopes` — de scopes waar het package mee werkt, gegroepeerd naar het LinkedIn-product dat ze verleent. `Scopes::MEMBER` is de set die elke app kan hebben.
- Scope-kennis op `LinkedInAccount`: `grantedScopes()`, `knowsScopes()`, `hasScope()`, `lacksScope()`, `canPostAsOrganization()`, `canListOrganizations()`. `hasScope()` en `lacksScope()` zijn bewust **niet** elkaars ontkenning — beide geven `false` als de scopes onbekend zijn, zodat het package nooit gokt.
- `LinkedIn::canListOrganizations()` en `LinkedIn::canPostAsOrganization()` — gate je UI hierop en niet op de config, anders bied je bestemmingen aan waar niet naar gepost kan worden.
- `LinkedInOAuth::authorizationUrl($state, $scopes)` en `connectFromCode($code, $requestedScopes)` nemen de scopes expliciet aan. Zonder dat was de config muteren tijdens een request de enige manier om minder te vragen.
- `?profile_only=1` op de ingebouwde connect-route vraagt alleen `Scopes::MEMBER` aan, zodat een app zonder de Community Management API tóch kan koppelen — op het profiel van het lid. LinkedIn weigert de *héle* autorisatie om één niet-geautoriseerde scope, dus minder vragen is de enige weg.
- `AuthorizationDenial::fromCallback($request)` leest een geweigerde autorisatie uit: `description` (HTML-gedecodeerd — LinkedIn escapet 'm, en rechtstreeks in Blade zag je de entiteiten), `isScopeProblem()`, `missingScope()` en `isRecoverableWithMemberScopes()`.
- `LinkedInScopeMissing` — wordt gegooid *vóór* het request uitgaat, wanneer de opgeslagen scopes bewijzen dat de call als 403 terugkomt. Bevat de `scope` en noemt het ontbrekende LinkedIn-product.
- `linkedin.session.scopes_key` — waar de ingebouwde flow onthoudt welke scopes zijn aangevraagd.

### Gewijzigd

- `organizations()` en posten als bedrijfspagina weigeren nu vooraf als het token de scope aantoonbaar mist, in plaats van een request af te vuren dat een 403 oplevert die niet te onderscheiden is van een verlopen token of een pagina die je niet beheert.
- `connectFromCode()` legt niet langer de *geconfigureerde* scopes vast als LinkedIn `scope` weglaat in de token-response, maar de scopes die voor die flow zijn aangevraagd. De config kan sinds de redirect gewijzigd zijn, en verkeerd gokken is erger dan niets weten — elke capability-check leunt op die kolom.

### Upgraden

Er breekt niets. Een koppeling van vóór 1.4 heeft geen vastgelegde scopes: `grantedScopes()` geeft `null`, zowel `hasScope()` als `lacksScope()` geven `false`, en geen enkele guard slaat aan — zo'n account blijft precies werken zoals voorheen. Koppel opnieuw om de capability-checks te activeren.

## [1.3.0] - 2026-07-13

### Toegevoegd

- Getypeerde excepties, zodat je op het **type** kunt reageren in plaats van op de fouttekst (die per release mag wijzigen). Ze erven allemaal van `LinkedInException`, dus een bestaande `catch (LinkedInException $e)` blijft werken:
  - `LinkedInNotConnected` — er is geen account gekoppeld.
  - `LinkedInConnectionExpired` — het token is verlopen en niet te vernieuwen; opnieuw koppelen is nodig. De enige fout waar een eindgebruiker zelf iets aan kan doen.
  - `LinkedInConfigurationException` — een vereiste instelling ontbreekt (bijv. posten op een bedrijfspagina zonder URN).
  - `LinkedInApiException` — LinkedIn gaf een fout terug. Bevat `operation` (`token`, `profile`, `publish`, `organizations`), `status`, `body` en `isAuthorizationProblem()` (401/403).

Applicaties die berichten in een andere taal tonen, kunnen deze typen nu op hun eigen teksten mappen in plaats van de Engelse tekst van het package te laten zien.

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
