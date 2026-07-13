# darvis/api-linkedin

> 🇬🇧 [English documentation](../../README.md)

Universele Laravel-koppeling om berichten op **LinkedIn** te plaatsen — op je
**persoonlijke profiel** én op een **bedrijfspagina** — via OAuth 2.0 en de
LinkedIn Posts API. Zonder externe dependencies (alleen de ingebouwde HTTP-client).

- OAuth 2.0 authorization-code flow met automatische token-refresh
- Posten namens een lid (`w_member_social`) of een organisatie (`w_organization_social`)
- Je bedrijfspagina's opvragen en per bericht kiezen waar het heen gaat
- Weet welke scopes LinkedIn écht heeft toegekend, zodat een app zonder Community Management API tóch koppelt — op het profiel
- Versleutelde tokenopslag in een `linkedin_accounts`-tabel
- Optionele, kant-en-klare connect/callback-routes
- `LinkedIn`-facade voor een one-liner post

## Installatie

```bash
composer require darvis/api-linkedin
```

Publiceer eventueel de config en migratie (de migratie wordt ook automatisch
geladen):

```bash
php artisan vendor:publish --tag=linkedin-config
php artisan vendor:publish --tag=linkedin-migrations
php artisan migrate
```

## LinkedIn-app instellen

1. Maak een app op [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps).
2. Vraag de producten **Share on LinkedIn** (profiel) en/of **Community Management API** (bedrijfspagina) aan.
3. Zet de redirect-URL op `https://jouw-domein.test/linkedin/callback` (of jouw eigen callback-route).
4. Vul je `.env`:

```dotenv
LINKEDIN_CLIENT_ID=...
LINKEDIN_CLIENT_SECRET=...
# Bedrijfspagina — laat leeg om alleen op je profiel te posten
LINKEDIN_ORGANIZATION_URN=urn:li:organization:1234567
# Geldige, recente API-versie (JJJJMM)
LINKEDIN_API_VERSION=202601
```

## Koppelen

Stuur de gebruiker naar de ingebouwde connect-route:

```blade
<a href="{{ route('linkedin.connect') }}">Verbind met LinkedIn</a>
```

Na afloop wordt teruggestuurd (zie `linkedin.routes.redirect_to`) met een
flash-bericht in `session('linkedin_status')` of `session('linkedin_error')`.

Wil je de flow zelf bedraden? Zet `linkedin.routes.enabled` op `false` en gebruik
`LinkedIn::authorizationUrl($state)` en `LinkedIn::connectFromCode($code)`.

### Als LinkedIn de hele autorisatie weigert

LinkedIn weigert de **héle** autorisatie zodra er één scope bij zit waarvoor je app
niet is geautoriseerd — de gebruiker komt dan niet eens bij het toestemmingsscherm.
Een app zonder de **Community Management API** kan dus helemaal niet koppelen zolang
de config om bedrijfspagina's vraagt, hoe onschuldig dat ook lijkt.

Koppel alleen met de profiel-scopes, en het werkt op elke app:

```blade
<a href="{{ route('linkedin.connect', ['profile_only' => 1]) }}">Koppel alleen met mijn profiel</a>
```

Bedraad je de flow zelf? Geef de scopes expliciet mee, en geef dezelfde set door aan
`connectFromCode()` — dan worden de toegekende scopes eerlijk vastgelegd, ook als
LinkedIn `scope` weglaat in de token-response:

```php
use Darvis\ApiLinkedin\Scopes;

$url = LinkedIn::authorizationUrl($state, Scopes::MEMBER);
// ...
$account = LinkedIn::connectFromCode($code, Scopes::MEMBER);
```

Lees de weigering uit in plaats van LinkedIn's Engelse tekst te parsen (die komt
HTML-geëscaped binnen, dus rechtstreeks in Blade zet zie je de entiteiten):

```php
use Darvis\ApiLinkedin\AuthorizationDenial;

if ($denial = AuthorizationDenial::fromCallback($request)) {
    $denial->description;                    // gedecodeerd, leesbaar
    $denial->isScopeProblem();               // je app mist een product — opnieuw proberen heeft geen zin
    $denial->missingScope();                 // 'w_organization_social'
    $denial->isRecoverableWithMemberScopes();// bied de profiel-only koppeling aan
}
```

## Wat de koppeling mag

De config zegt wat je *vraagt*; het token zegt wat je *krijgt*. Die twee lopen
uiteen zodra de LinkedIn-app een product mist, en het token beslist. Gate je UI dus
op de koppeling en nooit op de config alleen — anders bied je bestemmingen aan die
in een 403 eindigen:

```php
LinkedIn::canListOrganizations();   // config staat het toe én het token heeft r_organization_admin
LinkedIn::canPostAsOrganization();  // het token heeft w_organization_social

$account = LinkedIn::account();
$account->grantedScopes();          // ['openid', 'profile', 'w_member_social'] — of null als onbekend
$account->hasScope(Scopes::POST_AS_ORGANIZATION);
```

Een koppeling van vóór 1.4 heeft geen vastgelegde scopes. `grantedScopes()` is dan
`null` en zowel `hasScope()` als `lacksScope()` geven `false`: het package weigert in
beide richtingen te gokken, zodat er niets wordt aangeboden wat het niet kan beloven
en niets wordt geblokkeerd wat misschien nog werkt.

## Posten

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

// Op je persoonlijke profiel
LinkedIn::postAsMember("Nieuw blogartikel!\n\nhttps://example.com/blog/mijn-artikel");

// Op de standaard-bedrijfspagina (linkedin.organization_urn)
LinkedIn::postAsOrganization('Bedrijfsnieuws met een link https://example.com');

// Op een specifieke bedrijfspagina
LinkedIn::postAsOrganization('Bedrijfsnieuws', 'urn:li:organization:1234567');
```

Alle drie geven `['urn' => '...', 'permalink' => '...']` terug. Zet een URL in de
tekst; LinkedIn bouwt de linkpreview zelf uit de Open Graph-tags van de pagina.

> Tip: draai het posten in een queued job, zodat een trage of falende API-call je
> request niet blokkeert.

## Je bedrijfspagina's opvragen

Beheer je meerdere bedrijfspagina's en wil je de gebruiker laten kiezen? Zet het
opvragen aan en vraag LinkedIn welke pagina's het gekoppelde lid beheert:

```dotenv
LINKEDIN_ORGANIZATIONS_ENABLED=true
```

```php
LinkedIn::organizations();
// [
//   ['urn' => 'urn:li:organization:42', 'id' => '42', 'name' => 'Acme BV', 'vanity_name' => 'acme'],
//   ['urn' => 'urn:li:organization:99', 'id' => '99', 'name' => 'Acme Labs', 'vanity_name' => 'acme-labs'],
// ]

LinkedIn::organizations(fresh: true); // cache overslaan
LinkedIn::forgetOrganizations();      // cache legen
```

Combineer dit met `postAsOrganization($tekst, $urn)` om per bericht een
bestemming te laten kiezen.

> **Twee dingen om te weten.** Opvragen vereist de scope `r_organization_admin`,
> die alleen wordt aangevraagd als deze instelling aan staat en die
> Community Management API-toegang vereist. Zet je 'm áán ná het koppelen, dan
> heeft je bestaande token die scope niet — je moet dan opnieuw koppelen, en
> `organizations()` gooit tot die tijd een `LinkedInScopeMissing`. De lijst wordt
> `linkedin.organizations.cache_ttl` seconden gecachet (standaard een uur).

### Via de services (dependency injection)

```php
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;

public function share(LinkedInPublisher $publisher)
{
    $account = LinkedInAccount::current();

    $publisher->publish($account, $account->member_urn, 'Tekst met link https://example.com');
}
```

> Let op: de code van dit package (variabelen, comments, exception- en
> flash-berichten) is Engelstalig. Alleen de documentatie is tweetalig.

## Foutafhandeling

Alles gooit een `LinkedInException`, maar elke fout heeft zijn eigen type. Reageer
op het **type**, niet op de fouttekst — die mag per release wijzigen.

```php
use Darvis\ApiLinkedin\Exceptions\{
    LinkedInApiException,
    LinkedInConnectionExpired,
    LinkedInNotConnected,
    LinkedInScopeMissing,
};

try {
    LinkedIn::postAsMember($tekst);
} catch (LinkedInNotConnected) {
    // Er is nog geen account gekoppeld.
} catch (LinkedInConnectionExpired) {
    // Stuur de gebruiker opnieuw door de OAuth-flow — de enige fout die hij zelf kan oplossen.
} catch (LinkedInScopeMissing $e) {
    // $e->scope is nooit toegekend. Voeg het product toe aan de LinkedIn-app en koppel opnieuw;
    // er is geen request verstuurd, want LinkedIn had alleen maar 403 geantwoord.
} catch (LinkedInApiException $e) {
    // $e->operation  'token' | 'profile' | 'publish' | 'organizations'
    // $e->status     HTTP-status
    // $e->body       ruwe response-body
    if ($e->isAuthorizationProblem()) {   // 401/403: scope- of rechtenprobleem
        // ...
    }
}
```

| Exceptie | Betekenis |
| --- | --- |
| `LinkedInNotConnected` | Geen account gekoppeld |
| `LinkedInConnectionExpired` | Token verlopen en niet te vernieuwen — opnieuw koppelen |
| `LinkedInConfigurationException` | Een vereiste instelling ontbreekt |
| `LinkedInScopeMissing` | Het token mist aantoonbaar de scope die deze call nodig heeft (`scope`); wordt gegooid vóór er een request uitgaat |
| `LinkedInApiException` | LinkedIn gaf een fout terug (`operation`, `status`, `body`) |

Ze erven allemaal van `LinkedInException`, dus één `catch (LinkedInException $e)`
vangt nog steeds alles op.

## Configuratie

Alle sleutels staan in `config/linkedin.php`. Belangrijk:

| Sleutel | Omschrijving |
| --- | --- |
| `organization_urn` | Standaard-bedrijfspagina (URN); leeg = alleen profiel |
| `organizations.enabled` | Laat `LinkedIn::organizations()` je pagina's opvragen (voegt `r_organization_admin` toe) |
| `organizations.cache_ttl` | Seconden dat die lijst gecachet wordt; `0` = niet cachen |
| `api_version` | LinkedIn API-versie (JJJJMM) |
| `routes.enabled` | Ingebouwde connect/callback-routes aan/uit |
| `routes.prefix` / `routes.middleware` | Prefix en middleware van die routes |
| `routes.callback_name` | Routenaam voor de `redirect_uri` (bij eigen routes) |
| `routes.redirect_to` | Routenaam waarheen na de flow wordt teruggestuurd |
| `table` | Naam van de accounts-tabel |

## Testen

```bash
composer install
composer test
```

## Licentie

MIT © Arvid de Jong
