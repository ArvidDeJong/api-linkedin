---
name: api-linkedin-development
description: Work with darvis/api-linkedin. Use it to connect a LinkedIn account through OAuth, publish posts on a profile or a company page, attach an article card with your own image, list company pages, handle the typed exceptions and missing scopes, and test all of it without calling LinkedIn.
---

# darvis/api-linkedin development

## When to use this skill

Use this skill when code publishes on LinkedIn in an application that has `darvis/api-linkedin` installed, when you build the connect screen or your own OAuth callback, when a publish fails with a `LinkedInException` or comes back with an empty URN, when company pages or their scopes are involved, or when you write tests around any of this.

## How a publish runs

1. `LinkedIn::postAsMember()`, `postAsOrganization()` and `publish()` resolve the one stored connection with `LinkedInAccount::current()` (the row with the highest id). Without a row they throw `LinkedInNotConnected`. `postAsOrganization()` checks the URN first: without an argument and without `linkedin.organization_urn` it throws `LinkedInConfigurationException`, connected or not.
2. For an author that starts with `urn:li:organization:` the stored scopes are checked. When they are known and `w_organization_social` is not among them, `LinkedInScopeMissing` is thrown and no request goes out.
3. `LinkedInOAuth::freshAccessToken()` returns the stored token, or refreshes it when `token_expires_at` is less than a minute away. Without a usable refresh token it throws `LinkedInConnectionExpired`. A row without `token_expires_at` is never treated as expired.
4. The post goes to `https://api.linkedin.com/rest/posts` with the `LinkedIn-Version` header (`linkedin.api_version`) and `X-Restli-Protocol-Version: 2.0.0`. The reserved characters in the commentary are escaped by the package. Every post is `PUBLIC`, `MAIN_FEED` and `PUBLISHED`; there is no draft or connections-only option.
5. The result is `['urn' => ..., 'permalink' => ...]`. The URN comes from the `x-restli-id` response header, otherwise from `id` in the body. The permalink is `https://www.linkedin.com/feed/update/<urn>/`.

The package writes no log lines. The only place it reports anything is the built-in callback, which calls `report($e)` when the code exchange throws.

| Situation | You get | Message | Request sent |
| --- | --- | --- | --- |
| No stored connection | `LinkedInNotConnected` | `No active LinkedIn connection.` | no |
| `postAsOrganization()` without a URN and none configured | `LinkedInConfigurationException` | `No LinkedIn company page given, and none configured (linkedin.organization_urn).` | no |
| Organization author or image owner, scopes known, no `w_organization_social` | `LinkedInScopeMissing`, `$e->scope` | `The LinkedIn connection was not granted the "w_organization_social" scope (which requires the "Community Management API"). Reconnect the account after adding the product to your LinkedIn app.` | no |
| `organizations()`, scopes known, no `r_organization_admin` | `LinkedInScopeMissing`, `$e->scope` | the same sentence with `r_organization_admin` | no |
| Token expired, no refresh token or the refresh token expired | `LinkedInConnectionExpired` | `The LinkedIn connection has expired. Please reconnect the account.` | no |
| The token endpoint fails (code exchange or refresh) | `LinkedInApiException`, operation `token` | `LinkedIn returned an error while fetching the token: <body>` | yes |
| The userinfo call fails while connecting | `LinkedInApiException`, operation `profile` | `Could not fetch the LinkedIn profile: <body>` | yes |
| LinkedIn rejects the post | `LinkedInApiException`, operation `publish` | `LinkedIn rejected the post: <body>` | yes |
| The company page list fails | `LinkedInApiException`, operation `organizations` | `Could not fetch the LinkedIn company pages: <body>` | yes |
| Image upload fails | `LinkedInApiException`, operation `image` | `Could not initialize the LinkedIn image upload: <body>`, `LinkedIn returned no upload URL for the image` or `LinkedIn refused the image upload: <body>` | yes |
| 2xx without `x-restli-id` and without `id` | `['urn' => '', 'permalink' => '']` | none, nothing throws | yes |
| LinkedIn unreachable, timeout | Laravel's `Illuminate\Http\Client\ConnectionException`, not a `LinkedInException` | Laravel's | attempted |

`LinkedInApiException` carries `operation` (the `OPERATION_*` constants), `status`, `body` and `isAuthorizationProblem()` (true for 401 and 403). All package exceptions extend `Darvis\ApiLinkedin\Exceptions\LinkedInException`, a `RuntimeException`. Branch on the type; the messages are free to change.

## Publishing from a job

A publish is one or more HTTP calls to LinkedIn, so keep it out of the request.

```php
use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;

public function handle(): void
{
    try {
        $result = LinkedIn::postAsMember($this->post->text);
    } catch (LinkedInApiException $e) {
        if ($e->isAuthorizationProblem() || $e->status < 500) {
            $this->fail($e);   // a retry sends the same request into the same answer

            return;
        }

        throw $e;              // 5xx: let the queue retry
    } catch (LinkedInException $e) {
        $this->fail($e);       // not connected, expired, scope or config: a retry never helps

        return;
    }

    if ($result['urn'] === '') {
        // LinkedIn accepted the post but gave no id back; don't store an empty permalink.
        return;
    }

    $this->post->update(['linkedin_urn' => $result['urn'], 'linkedin_url' => $result['permalink']]);
}
```

A job that retries after the post went out publishes it twice; the package has no duplicate check. Store the URN before anything else in the job can fail.

## Posting as a company page

```php
LinkedIn::postAsOrganization('Company news');                               // linkedin.organization_urn
LinkedIn::postAsOrganization('Company news', 'urn:li:organization:1234567');
LinkedIn::publish('urn:li:organization:1234567', 'Company news');           // any author URN
```

There is no separate page token: the member's token is used with another `author`. Offer the option on `LinkedIn::canPostAsOrganization()`, which asks the stored token, not on `LinkedIn::organizationEnabled()`, which only says that a URN is configured.

## An article card with your own image

```php
use Darvis\ApiLinkedin\Article;
use Darvis\ApiLinkedin\Facades\LinkedIn;

$author = 'urn:li:organization:1234567';

$image = LinkedIn::uploadImage($author, Storage::get('og/article.jpg'), 'image/jpeg');

$article = Article::to('https://example.com/blog/my-article')
    ->withTitle('My article')
    ->withDescription('What it is about')
    ->withThumbnail($image);

LinkedIn::publish($author, 'New on the blog', $article);
```

- `uploadImage(string $ownerUrn, string $contents, string $contentType = 'application/octet-stream')` takes bytes, not a path, and returns a `urn:li:image:...`. Pass the real content type.
- The owner must be the author of the post. Posting the same article as the member and as a page means two uploads.
- `Article` is immutable and has a private constructor: start with `Article::to($url)`; every `with...()` returns a new instance. Empty fields are left out of the payload.
- An upload is two requests: `POST /rest/images?action=initializeUpload`, then a `PUT` to the returned `uploadUrl` on another host.

## Listing company pages

```php
if (LinkedIn::canListOrganizations()) {
    $pages = LinkedIn::organizations();            // cached per account
    $pages = LinkedIn::organizations(fresh: true); // drops the cache first
}
// [['urn' => 'urn:li:organization:42', 'id' => '42', 'name' => 'Acme', 'vanity_name' => 'acme'], ...]
```

`canListOrganizations()` needs both `linkedin.organizations.enabled` and `r_organization_admin` on the token. `organizations()` itself only looks at the token. When LinkedIn leaves the details out, `name` is the URN and `vanity_name` is null. The cache key is `linkedin.organizations.<account id>` and lives `linkedin.organizations.cache_ttl` seconds; `0` turns the cache off.

## Connecting

The built-in routes are `linkedin.connect` (`GET /linkedin/connect`) and `linkedin.callback` (`GET /linkedin/callback`). The callback redirects to the route named in `linkedin.routes.redirect_to`, or to `/`, with a flash message:

| Outcome | Session key | Message |
| --- | --- | --- |
| Connected | `linkedin_status` | `LinkedIn connected as <name>.` |
| Credentials missing | `linkedin_error` | `Configure LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET first.` |
| LinkedIn refused | `linkedin_error` | `LinkedIn connection denied: <description>`, plus a sentence about the Community Management API when member scopes would have worked |
| No code, or the state does not match | `linkedin_error` | `Invalid or expired connection session. Please try again.` |
| The code exchange threw | `linkedin_error` | `Connecting failed: <exception message>` (also passed to `report()`) |

```blade
@if (session('linkedin_error'))
    <p class="text-red-600">{{ session('linkedin_error') }}</p>
@endif

<a href="{{ route('linkedin.connect') }}">Connect LinkedIn</a>
<a href="{{ route('linkedin.connect', ['profile_only' => 1]) }}">Connect my profile only</a>
```

`profile_only=1` asks for `Scopes::MEMBER` (`openid`, `profile`, `w_member_social`) whatever the config says. That is the way in for a LinkedIn app without the Community Management API: LinkedIn refuses the whole authorization over one scope the app does not hold.

Your own flow, with `linkedin.routes.enabled` set to false:

```php
use Darvis\ApiLinkedin\AuthorizationDenial;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Scopes;

// connect
$state = Str::random(40);
session(['linkedin_state' => $state, 'linkedin_scopes' => Scopes::MEMBER]);

return redirect()->away(LinkedIn::authorizationUrl($state, Scopes::MEMBER));

// callback; this route must carry the name from linkedin.routes.callback_name
if ($denial = AuthorizationDenial::fromCallback($request)) {
    // $denial->error, $denial->description (HTML-decoded), isScopeProblem(),
    // missingScope(), isRecoverableWithMemberScopes()
    return back()->with('error', (string) $denial);
}

$expected = session()->pull('linkedin_state');

abort_unless(is_string($expected) && hash_equals($expected, (string) $request->string('state')), 403);

$account = LinkedIn::connectFromCode((string) $request->string('code'), session()->pull('linkedin_scopes'));
```

## Pitfalls

- **The connect route is open.** The default middleware is `['web']` only, and whoever completes the flow becomes the connection the whole application posts with. Set `linkedin.routes.middleware` to something like `['web', 'auth', 'can:manage-linkedin']` in the published config.
- **One connection, and "current" means the highest id.** Connecting a second member adds a row and that row wins. Connecting a member that already has an older row updates that row and does not make it current. Call `LinkedIn::disconnect()` before connecting another account.
- **`disconnect()` only deletes the rows.** It sends nothing to LinkedIn and does not clear the cached page list. `forgetOrganizations()` needs the account, so call it before `disconnect()`, not after.
- **`hasScope()` and `lacksScope()` are both false when the scopes are unknown** (`grantedScopes()` is null, a row from before 1.4). Offer features on `hasScope()`, `canPostAsOrganization()` and `canListOrganizations()`; don't write `! $account->hasScope(...)` to block something. With unknown scopes the guards let the call through, and a missing scope then surfaces as a `LinkedInApiException` with status 403.
- **A token never gains scopes.** Setting `LINKEDIN_ORGANIZATION_URN` or `LINKEDIN_ORGANIZATIONS_ENABLED` after connecting changes what the next authorization asks for, not what the stored token may do. Reconnect; don't retry and don't edit the `scopes` column.
- **Pass the requested scopes to `connectFromCode()`.** Without the second argument, and when LinkedIn leaves `scope` out of the token response, the scopes the config implies at that moment are stored, which may not be what was asked.
- **The redirect URI is `route(<callback_name>)`.** It has to match the LinkedIn app exactly. With the routes disabled and no route of that name, `authorizationUrl()` throws Laravel's `RouteNotFoundException`. Prefix, middleware and route names are read when the provider boots.
- **Don't escape the commentary yourself.** The package escapes `\ | { } @ [ ] ( ) < > # * _ ~`, every time. Mention syntax such as `@[Acme](urn:li:organization:1)` therefore arrives as literal text.
- **Connection errors are not wrapped.** Catching `LinkedInException` alone misses a timeout.
- **`APP_KEY` rotation breaks the connection.** `access_token` and `refresh_token` are `encrypted` casts; reading them with another key throws Laravel's `DecryptException`. Reconnect after a rotation. Go through the facade or `freshAccessToken()`, never `$account->access_token`.
- **The table name comes from `linkedin.table`**, for the model and the migration. Don't query `linkedin_accounts` by name.

## Settings

Ask the facade what matters in app code: `LinkedIn::isConfigured()` (client id and secret are filled), `LinkedIn::organizationEnabled()` (a default page URN is set) and `LinkedIn::organizationListingEnabled()`. The package itself reads everything through `Darvis\ApiLinkedin\Support\LinkedInConfig`, with accessors such as `apiVersion()`, `organizationUrn()`, `organizationsCacheTtl()`, `routeMiddleware()`, `redirectTo()`, `statusKey()` and `errorKey()`; an empty string counts as not set.

| Env | Key | Default |
| --- | --- | --- |
| `LINKEDIN_CLIENT_ID` | `client_id` | none |
| `LINKEDIN_CLIENT_SECRET` | `client_secret` | none |
| `LINKEDIN_ORGANIZATION_URN` | `organization_urn` | none; set, it adds `w_organization_social` to the request |
| `LINKEDIN_API_VERSION` | `api_version` | `202601`, format `YYYYMM` |
| `LINKEDIN_ORGANIZATIONS_ENABLED` | `organizations.enabled` | `false`; on, it adds `w_organization_social` and `r_organization_admin` |
| `LINKEDIN_ORGANIZATIONS_CACHE_TTL` | `organizations.cache_ttl` | `3600` |
| `LINKEDIN_ROUTES_ENABLED` | `routes.enabled` | `true` |
| `LINKEDIN_ROUTE_PREFIX` | `routes.prefix` | `linkedin` |

Without an env variable: `routes.middleware` (`['web']`), `routes.connect_name`, `routes.callback_name`, `routes.redirect_to` (null), `scopes` (extra scopes, `[]`), `session.state_key`, `session.scopes_key`, `session.status_key`, `session.error_key` and `table`. Publish the config with `php artisan vendor:publish --tag=linkedin-config`, the migration with `--tag=linkedin-migrations`; the migration also loads from the package.

## Testing

Never call LinkedIn from a test. Every call goes through Laravel's HTTP client, so `Http::fake()` covers all of it.

```php
use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Illuminate\Support\Facades\Http;

function linkedInAccount(array $attributes = []): LinkedInAccount
{
    return LinkedInAccount::create($attributes + [
        'member_id' => '12345',
        'member_urn' => 'urn:li:person:12345',
        'name' => 'Test Member',
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'scopes' => 'openid profile w_member_social w_organization_social',
        'token_expires_at' => now()->addDays(30),
        'refresh_token_expires_at' => now()->addYear(),
    ]);
}

it('publishes on the profile', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:999']),
    ]);

    linkedInAccount();

    expect(LinkedIn::postAsMember('Price (excl. VAT)')['permalink'])
        ->toBe('https://www.linkedin.com/feed/update/urn:li:share:999/');

    Http::assertSent(fn ($request) => $request['author'] === 'urn:li:person:12345'
        && $request['commentary'] === 'Price \\(excl. VAT\\)');
});

it('reports a rejected post', function () {
    Http::fake(['api.linkedin.com/rest/posts' => Http::response(['message' => 'Nope'], 403)]);

    linkedInAccount();

    expect(fn () => LinkedIn::postAsMember('Text'))->toThrow(
        fn (LinkedInApiException $e) => expect($e->operation)->toBe('publish')
            ->and($e->isAuthorizationProblem())->toBeTrue()
    );
});
```

- Fake the `x-restli-id` header, or the URN and the permalink come back empty.
- The other endpoints: `www.linkedin.com/oauth/v2/accessToken` (`access_token`, `expires_in`, `refresh_token`, `refresh_token_expires_in`, `scope`), `api.linkedin.com/v2/userinfo` (`sub`, `name`), `api.linkedin.com/rest/organizationAcls*` (`elements`, each with `organization` and `organization~` holding `id`, `localizedName`, `vanityName`) and `api.linkedin.com/rest/images*` (`value.uploadUrl`, `value.image`), plus the upload URL you hand out yourself.
- To test the scope guard, store `'scopes' => 'openid profile w_member_social'`, post as `urn:li:organization:1`, expect `LinkedInScopeMissing` and `Http::assertNothingSent()`.
- To test a refresh, store `'token_expires_at' => now()->subHour()` and fake the token endpoint. For `LinkedInConnectionExpired`, also set `'refresh_token' => null`.
- To test the callback, put the state in the session first: `$this->withSession(['linkedin_oauth_state' => 'abc'])->get(route('linkedin.callback', ['code' => 'c', 'state' => 'abc']))->assertSessionHas('linkedin_status')`.
- Change settings with `config(['linkedin.organizations.cache_ttl' => 0])` inside the test; with the cache on, a second `organizations()` call in the same test is served from the cache and sends no request.
- The tokens are encrypted, so the test environment needs an `app.key`.
