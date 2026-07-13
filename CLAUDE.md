# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Wat dit is

`darvis/api-linkedin` — een standalone Laravel-package (geen app) waarmee een Laravel-project berichten op LinkedIn plaatst: op het persoonlijke profiel én op een bedrijfspagina, via OAuth 2.0 en de Posts API. Getest tegen Laravel 11/12/13 op PHP 8.2+.

**Taalconventie:** alle comments, docblocks, exception-berichten, commit-teksten en documentatie zijn in het Nederlands. Houd dat aan; code-identifiers blijven Engels.

**Geen externe HTTP-dependency, met opzet.** Alles loopt via `Illuminate\Support\Facades\Http`. Voeg geen Guzzle-wrapper, socialite-provider of LinkedIn-SDK toe.

## Commando's

```bash
composer install
composer test                                    # pest, hele suite

vendor/bin/pest tests/LinkedInOAuthTest.php      # één bestand
vendor/bin/pest --filter="vernieuwt een verlopen token"   # één test (Nederlandse omschrijving)
```

Er is geen linter/formatter geconfigureerd in dit package.

## Architectuur

De keten is `Facade LinkedIn` → `LinkedInManager` → (`LinkedInOAuth` | `LinkedInPublisher`) → `LinkedInAccount`. Alle drie de services zijn singletons; `LinkedInManager` is als `'linkedin'` gealiast in de container.

- [LinkedInManager.php](src/LinkedInManager.php) — ergonomische gevel (`postAsMember()`, `postAsOrganization()`, `publish()`). Bevat geen HTTP-logica; resolvet het account en delegeert.
- [Services/LinkedInOAuth.php](src/Services/LinkedInOAuth.php) — autorisatie-URL, code inwisselen, profiel ophalen, token verversen.
- [Services/LinkedInPublisher.php](src/Services/LinkedInPublisher.php) — `POST /rest/posts`.
- [Models/LinkedInAccount.php](src/Models/LinkedInAccount.php) — de opgeslagen verbinding.

### Eén globale verbinding, geen verbinding per gebruiker

`LinkedInAccount::current()` is simpelweg `latest('id')->first()` — het package gaat uit van **één actieve koppeling voor de hele applicatie**, niet van een account per ingelogde gebruiker. `disconnect()` truncate de tabel. Wie multi-tenant / per-user koppelingen wil, moet dit ophaalpad aanpassen (`current()`, `LinkedInManager::requireAccount()`, `disconnect()`), niet alleen een kolom toevoegen.

### Bedrijfspagina posten leunt op hetzelfde lid-token

Er is geen apart organisatie-token. `postAsOrganization()` gebruikt het access-token van het gekoppelde lid en zet enkel een andere `author`-URN (uit `config('linkedin.organization_urn')`). Dat werkt alleen als het token de scope `w_organization_social` heeft én het lid beheerder van de pagina is.

Gevolg dat makkelijk misgaat: **`scopes()` wordt dynamisch afgeleid van `organization_urn`.** Wordt die config pas ná het koppelen ingevuld, dan zit `w_organization_social` niet in het bestaande token en faalt het posten — opnieuw koppelen is dan nodig. Een niet-obvious koppeling tussen config en tokenstatus.

### Token-lifecycle

`LinkedInPublisher::publish()` roept altijd eerst `LinkedInOAuth::freshAccessToken()` aan. Die ververst automatisch bij een verlopen token (met een minuut marge, zie `tokenHasExpired()`) en gooit een `LinkedInException` als er geen bruikbaar refresh-token is. Nieuwe API-calls horen via dit pad te lopen; lees nooit rechtstreeks `$account->access_token` uit.

Tokens staan `encrypted` in de casts, dus de applicatie heeft een `APP_KEY` nodig en de kolommen zijn `text`.

### Redirect-URI is afgeleid, niet geconfigureerd

`LinkedInOAuth::redirectUri()` is `route(config('linkedin.routes.callback_name'))`. Er is dus geen `LINKEDIN_REDIRECT_URI`-env; de callback-route bepaalt de URI, en die moet exact overeenkomen met de redirect-URL in de LinkedIn-app. Zet je `routes.enabled` op `false` om de flow zelf te bedraden, dan moet je eigen route de naam uit `routes.callback_name` dragen — anders klopt de `redirect_uri` in beide OAuth-calls niet.

De ingebouwde routes worden in `LinkedInServiceProvider::registerRoutes()` geregistreerd met prefix/middleware uit config; de routenamen zelf komen uit config binnen [routes/web.php](routes/web.php).

### LinkedIn-eigenaardigheden die in de code zijn vastgelegd

- **Commentary-escaping** (`LinkedInPublisher::escapeCommentary()`): de Posts API reserveert `| { } @ [ ] ( ) < > # * _ ~` en `\`. De backslash wordt als eerste vervangen, anders raken bestaande backslashes dubbel geëscaped — laat die volgorde intact.
- **De post-URN komt uit de `x-restli-id` responseheader**, niet uit de body (de body is bij een 201 leeg).
- **`LinkedIn-Version`-header** (`config('linkedin.api_version')`, formaat `JJJJMM`) is verplicht op `/rest/*` en verloopt na ~1 jaar. Een plots falende publish is vaak een verlopen versie, niet een bug.
- Linkpreviews worden niet meegestuurd: een URL in de tekst laat LinkedIn zelf de Open Graph-tags ophalen.

### Tabelnaam is configureerbaar

`config('linkedin.table')` wordt zowel door `LinkedInAccount::getTable()` als door de migratie gelezen. Hardcode de tabelnaam nergens.

## Tests

Pest bovenop Orchestra Testbench. [tests/Pest.php](tests/Pest.php) hangt `TestCase` + `RefreshDatabase` aan álle tests in `tests/`; [tests/TestCase.php](tests/TestCase.php) draait op in-memory SQLite en zet een `app.key` plus geldige LinkedIn-config (inclusief `organization_urn`) — een test die het gedrag *zonder* bedrijfspagina wil zien, moet die config expliciet leegzetten.

Alle LinkedIn-calls worden met `Http::fake()` afgevangen; er gaat nooit echt verkeer naar LinkedIn. Fake bij een publish-test ook de `x-restli-id`-header, anders is de URN leeg.
