# darvis/api-linkedin

> 🇬🇧 [English documentation](../../README.md)

Universele Laravel-koppeling om berichten op **LinkedIn** te plaatsen — op je
**persoonlijke profiel** én op een **bedrijfspagina** — via OAuth 2.0 en de
LinkedIn Posts API. Zonder externe dependencies (alleen de ingebouwde HTTP-client).

- OAuth 2.0 authorization-code flow met automatische token-refresh
- Posten namens een lid (`w_member_social`) of een organisatie (`w_organization_social`)
- Je bedrijfspagina's opvragen en per bericht kiezen waar het heen gaat
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
> heeft je bestaande token die scope niet — je moet dan opnieuw koppelen. De
> lijst wordt `linkedin.organizations.cache_ttl` seconden gecachet (standaard een
> uur).

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
};

try {
    LinkedIn::postAsMember($tekst);
} catch (LinkedInNotConnected) {
    // Er is nog geen account gekoppeld.
} catch (LinkedInConnectionExpired) {
    // Stuur de gebruiker opnieuw door de OAuth-flow — de enige fout die hij zelf kan oplossen.
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
