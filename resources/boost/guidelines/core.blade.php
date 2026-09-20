## darvis/api-linkedin

This package publishes posts on LinkedIn, on the connected member's profile or on a company page, through OAuth 2.0 and the Posts API. It talks to LinkedIn through `Illuminate\Support\Facades\Http` only; don't add a Guzzle wrapper, a Socialite provider or a LinkedIn SDK next to it.

- Config lives under the key `linkedin` (file `config/linkedin.php`). Read it through `Darvis\ApiLinkedin\LinkedInManager` (`app('linkedin')`, facade `Darvis\ApiLinkedin\Facades\LinkedIn`), not through the config helper in app code. Inside the package itself every setting comes from `Darvis\ApiLinkedin\Support\LinkedInConfig`, which holds the defaults; nothing else reads the config.
- There is one global connection for the whole application, not one per user. `LinkedIn::account()` returns it (or null), `LinkedIn::isConnected()` tells whether there is one, `LinkedIn::disconnect()` removes it.
- The config says which scopes are requested; the stored token says which scopes LinkedIn granted, and the token wins. Gate UI and features on the connection, never on the config:

@verbatim
<code-snippet name="Offer only what the token allows" lang="php">
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Scopes;

LinkedIn::canPostAsOrganization();  // the token carries w_organization_social
LinkedIn::canListOrganizations();   // config allows it AND the token carries r_organization_admin
LinkedIn::account()?->hasScope(Scopes::POST_AS_ORGANIZATION);
</code-snippet>
@endverbatim

- A token never gains scopes. When a scope is missing (`LinkedInScopeMissing`), the only fix is reconnecting; don't retry the call and don't "fix" it by changing the config.
- Publishing: `LinkedIn::postAsMember($commentary)`, `LinkedIn::postAsOrganization($commentary, $organizationUrn = null)` (defaults to `linkedin.organization_urn`) and `LinkedIn::publish($authorUrn, $commentary)`. All return `['urn' => …, 'permalink' => …]`. Run them in a queued job; a slow or failing LinkedIn call must not block a request.
- A bare URL in the commentary makes LinkedIn build the link preview from the page's Open Graph tags. To control the card, attach an `Article`; its thumbnail is an image URN from `LinkedIn::uploadImage($ownerUrn, $contents, $contentType)`, and the owner must equal the author the post is published as:

@verbatim
<code-snippet name="Post a link card with your own image" lang="php">
use Darvis\ApiLinkedin\Article;
use Darvis\ApiLinkedin\Facades\LinkedIn;

$owner = LinkedIn::account()->member_urn;
$image = LinkedIn::uploadImage($owner, Storage::get('og/article.jpg'), 'image/jpeg');

$article = Article::to('https://example.com/blog/my-article')
    ->withTitle('My article')
    ->withDescription('What it is about')
    ->withThumbnail($image);

LinkedIn::postAsMember('New on the blog', $article);
</code-snippet>
@endverbatim

- Every failure throws a subclass of `Darvis\ApiLinkedin\Exceptions\LinkedInException`: `LinkedInNotConnected`, `LinkedInConnectionExpired` (the one an end user can act on: reconnect), `LinkedInConfigurationException`, `LinkedInScopeMissing` (carries `scope`) and `LinkedInApiException` (carries `operation`, `status`, `body`). Branch on the type, never on the message text.
- The built-in routes `linkedin.connect` and `linkedin.callback` live under the prefix and middleware from `linkedin.routes`. The redirect URI is derived from the callback route and must match the LinkedIn app exactly; there is no env var for it. With `linkedin.routes.enabled` false, your own callback route must carry the name from `linkedin.routes.callback_name`.
- An app without the Community Management API cannot connect while company page scopes are requested: link to `route('linkedin.connect', ['profile_only' => 1])`, or pass `Scopes::MEMBER` to both `authorizationUrl()` and `connectFromCode()`. Read a refused authorization with `Darvis\ApiLinkedin\AuthorizationDenial::fromCallback($request)` instead of parsing LinkedIn's text.
- Tokens are stored encrypted, so the application needs an `APP_KEY`. Never read `$account->access_token` directly.
- `LINKEDIN_API_VERSION` (format `YYYYMM`) expires after about a year. A publish that suddenly fails with a 4xx is often an expired version, not a bug.
