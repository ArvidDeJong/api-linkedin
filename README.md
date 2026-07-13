# darvis/api-linkedin

> 🇳🇱 [Nederlandse documentatie](docs/nl/README.md)

Universal Laravel integration for publishing posts on **LinkedIn** — both on your
**personal profile** and on a **company page** — through OAuth 2.0 and the
LinkedIn Posts API. Without external dependencies (only the built-in HTTP client).

- OAuth 2.0 authorization code flow with automatic token refresh
- Post on behalf of a member (`w_member_social`) or an organization (`w_organization_social`)
- List the company pages you administer and pick one per post
- Knows which scopes LinkedIn actually granted, so an app without the Community Management API still connects — on the profile
- Encrypted token storage in a `linkedin_accounts` table
- Optional, ready-to-use connect/callback routes
- `LinkedIn` facade for a one-liner post

## Installation

```bash
composer require darvis/api-linkedin
```

Optionally publish the config and the migration (the migration is loaded
automatically as well):

```bash
php artisan vendor:publish --tag=linkedin-config
php artisan vendor:publish --tag=linkedin-migrations
php artisan migrate
```

## Setting up the LinkedIn app

1. Create an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps).
2. Request the products **Share on LinkedIn** (profile) and/or **Community Management API** (company page).
3. Set the redirect URL to `https://your-domain.test/linkedin/callback` (or your own callback route).
4. Fill in your `.env`:

```dotenv
LINKEDIN_CLIENT_ID=...
LINKEDIN_CLIENT_SECRET=...
# Company page — leave empty to post on your profile only
LINKEDIN_ORGANIZATION_URN=urn:li:organization:1234567
# Valid, recent API version (YYYYMM)
LINKEDIN_API_VERSION=202601
```

## Connecting

Send the user to the built-in connect route:

```blade
<a href="{{ route('linkedin.connect') }}">Connect with LinkedIn</a>
```

Afterwards the user is redirected back (see `linkedin.routes.redirect_to`) with a
flash message in `session('linkedin_status')` or `session('linkedin_error')`.

Want to wire the flow yourself? Set `linkedin.routes.enabled` to `false` and use
`LinkedIn::authorizationUrl($state)` and `LinkedIn::connectFromCode($code)`.

### When LinkedIn refuses the whole authorization

LinkedIn rejects the **entire** authorization when a single requested scope is not
authorized for your app — the member never even reaches the consent screen. So an
app without the **Community Management API** cannot connect at all as long as the
config asks for company pages, however harmless that seems.

Connect with the member scopes only, and it works on any app:

```blade
<a href="{{ route('linkedin.connect', ['profile_only' => 1]) }}">Connect with my profile only</a>
```

Wiring the flow yourself? Pass the scopes explicitly, and hand the same set to
`connectFromCode()` so the granted scopes are recorded truthfully even when
LinkedIn leaves `scope` out of the token response:

```php
use Darvis\ApiLinkedin\Scopes;

$url = LinkedIn::authorizationUrl($state, Scopes::MEMBER);
// ...
$account = LinkedIn::connectFromCode($code, Scopes::MEMBER);
```

Read the denial instead of parsing LinkedIn's English text (which arrives
HTML-escaped, so echoing it straight into Blade shows the entities):

```php
use Darvis\ApiLinkedin\AuthorizationDenial;

if ($denial = AuthorizationDenial::fromCallback($request)) {
    $denial->description;                    // decoded, human-readable
    $denial->isScopeProblem();               // your app lacks a product — retrying is pointless
    $denial->missingScope();                 // 'w_organization_social'
    $denial->isRecoverableWithMemberScopes();// offer the profile-only connect
}
```

## What the connection may do

The config says what to *ask* for; the token says what you *got*. They drift apart
the moment the LinkedIn app misses a product, and it is the token that decides.
Gate your UI on the connection, never on the config alone — otherwise you offer
targets that publish into a 403:

```php
LinkedIn::canListOrganizations();   // config allows it AND the token carries r_organization_admin
LinkedIn::canPostAsOrganization();  // the token carries w_organization_social

$account = LinkedIn::account();
$account->grantedScopes();          // ['openid', 'profile', 'w_member_social'] — or null when unknown
$account->hasScope(Scopes::POST_AS_ORGANIZATION);
```

A connection stored before 1.4 has no recorded scopes. `grantedScopes()` is then
`null` and both `hasScope()` and `lacksScope()` return `false`: the package refuses
to guess in either direction, so nothing is offered that it cannot promise and
nothing is blocked that might still work.

## Publishing

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

// On your personal profile
LinkedIn::postAsMember("New blog article!\n\nhttps://example.com/blog/my-article");

// On the default company page (linkedin.organization_urn)
LinkedIn::postAsOrganization('Company news with a link https://example.com');

// On a specific company page
LinkedIn::postAsOrganization('Company news', 'urn:li:organization:1234567');
```

All of these return `['urn' => '...', 'permalink' => '...']`. Put a URL in the text
and LinkedIn builds the link preview itself from the Open Graph tags of that page.

> Tip: run the publishing in a queued job, so a slow or failing API call does not
> block your request.

### With your own image: an article card

Letting LinkedIn crawl your Open Graph tags works, but only if LinkedIn can reach
the page, it caches the result per URL, and you have no say over the image. Attach
an `Article` instead: you supply the thumbnail, and the whole card stays clickable
through to your site.

```php
use Darvis\ApiLinkedin\Article;
use Darvis\ApiLinkedin\Facades\LinkedIn;

$author = LinkedIn::account()->member_urn;

$article = Article::to('https://example.com/blog/my-article')
    ->withTitle('My article')
    ->withDescription('A short summary.')
    ->withThumbnail(LinkedIn::uploadImage($author, file_get_contents($path), 'image/png'));

LinkedIn::postAsMember("New blog article!", $article);
```

Two things that bite if you skip them:

- **The image owner must be the author of the post.** `uploadImage()` takes the
  owner URN for that reason — posting as a company page means uploading as that
  company page. Sharing one upload across authors does not work.
- **Uploading needs no extra scope.** It rides on the same `w_member_social` /
  `w_organization_social` the post itself needs, so no reconnect is required.

The thumbnail is optional: an `Article` without one still renders a clickable card,
and LinkedIn falls back to crawling the page for an image. Omit the `Article`
entirely and you get exactly the pre-1.5 behaviour.

`uploadImage()` takes the raw bytes, not a path — the package does no filesystem
work, so the image may come from disk, S3, or anywhere else.

## Listing your company pages

Do you administer several company pages and want the user to pick one? Turn the
listing on and ask LinkedIn which pages the connected member administers:

```dotenv
LINKEDIN_ORGANIZATIONS_ENABLED=true
```

```php
LinkedIn::organizations();
// [
//   ['urn' => 'urn:li:organization:42', 'id' => '42', 'name' => 'Acme BV', 'vanity_name' => 'acme'],
//   ['urn' => 'urn:li:organization:99', 'id' => '99', 'name' => 'Acme Labs', 'vanity_name' => 'acme-labs'],
// ]

LinkedIn::organizations(fresh: true); // bypass the cache
LinkedIn::forgetOrganizations();      // drop the cached list
```

Combine it with `postAsOrganization($text, $urn)` to let a user choose a target
per post.

> **Two things to know.** Listing requires the `r_organization_admin` scope, which
> is only requested when this setting is on and requires Community Management API
> access. Turning it on **after** connecting means your existing token does not
> carry the scope — you have to reconnect, and `organizations()` throws
> `LinkedInScopeMissing` until you do. The list is cached for
> `linkedin.organizations.cache_ttl` seconds (one hour by default).

### Through the services (dependency injection)

```php
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;

public function share(LinkedInPublisher $publisher)
{
    $account = LinkedInAccount::current();

    $publisher->publish($account, $account->member_urn, 'Text with a link https://example.com');
}
```

## Error handling

Everything throws a `LinkedInException`, but each failure has its own type — react
on the **type**, not on the message text (that is free to change between releases).

```php
use Darvis\ApiLinkedin\Exceptions\{
    LinkedInApiException,
    LinkedInConnectionExpired,
    LinkedInNotConnected,
    LinkedInScopeMissing,
};

try {
    LinkedIn::postAsMember($text);
} catch (LinkedInNotConnected) {
    // Nobody connected an account yet.
} catch (LinkedInConnectionExpired) {
    // Send the user back through the OAuth flow — the only failure they can fix.
} catch (LinkedInScopeMissing $e) {
    // $e->scope was never granted. Add the product to the LinkedIn app and reconnect;
    // no request was sent, because LinkedIn would only have answered 403.
} catch (LinkedInApiException $e) {
    // $e->operation  'token' | 'profile' | 'publish' | 'organizations'
    // $e->status     HTTP status
    // $e->body       raw response body
    if ($e->isAuthorizationProblem()) {   // 401/403: scope or permission issue
        // ...
    }
}
```

| Exception | Meaning |
| --- | --- |
| `LinkedInNotConnected` | No account connected |
| `LinkedInConnectionExpired` | Token expired and not refreshable — reconnect |
| `LinkedInConfigurationException` | A required setting is missing |
| `LinkedInScopeMissing` | The token provably lacks the scope this call needs (`scope`); thrown before any request goes out |
| `LinkedInApiException` | LinkedIn returned an error (`operation`, `status`, `body`) |

All of them extend `LinkedInException`, so a single `catch (LinkedInException $e)`
still catches everything.

## Configuration

All keys live in `config/linkedin.php`. The important ones:

| Key | Description |
| --- | --- |
| `organization_urn` | Default company page URN; empty = profile only |
| `organizations.enabled` | Allow `LinkedIn::organizations()` to list your pages (adds `r_organization_admin`) |
| `organizations.cache_ttl` | Seconds to cache that list; `0` = no cache |
| `api_version` | LinkedIn API version (YYYYMM) |
| `routes.enabled` | Turn the built-in connect/callback routes on/off |
| `routes.prefix` / `routes.middleware` | Prefix and middleware of those routes |
| `routes.callback_name` | Route name used for the `redirect_uri` (when using your own routes) |
| `routes.redirect_to` | Route name to redirect back to after the flow |
| `table` | Name of the accounts table |

## Testing

```bash
composer install
composer test
```

## License

MIT © Arvid de Jong
