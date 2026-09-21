---
title: "Connecting"
description: "Connect a LinkedIn account to a Laravel app: who may use the connect route, the flash messages, your own OAuth routes, profile-only connects and token scopes."
nav_order: 4
---

# Connecting

Connecting is the OAuth 2.0 flow in which a LinkedIn member gives your application permission to post. The package then stores an access token for that member.

The package keeps **one connection for the whole application**, not one per user: `LinkedIn::account()` returns the account that was connected most recently, also when that member had connected before, and `LinkedIn::disconnect()` removes every stored connection together with its cached list of company pages. A multi-tenant setup needs its own retrieval path and is out of scope.

## The built-in routes

Send a signed-in user to the connect route. LinkedIn asks for consent and returns to the callback route, which stores the connection:

```php
$url = route('linkedin.connect'); // https://your-domain.test/linkedin/connect
```

Afterwards the user is redirected to the route named in `linkedin.routes.redirect_to`, or to `/` when that is empty. A flash message (a session value that lives for one request) is in `session('linkedin_status')` on success, `LinkedIn connected as <name>.`, or in `session('linkedin_error')` on failure. The callback compares the OAuth `state` with the one in the session and refuses a mismatch. The [quick start](quickstart.md) has a view that shows both messages.

The routes live under the prefix and middleware from `linkedin.routes`: `/linkedin/connect` and `/linkedin/callback` by default.

### Who may connect

Whoever completes the flow becomes the connection the whole application posts with. The routes therefore run through `['web', 'auth']` by default since 1.8: a guest is sent to your `login` route, or gets a 401 when the request expects JSON. `auth` is Laravel's [authentication middleware](https://laravel.com/docs/authentication#protecting-routes). Signed in is usually still too wide, so publish the config (`php artisan vendor:publish --tag=linkedin-config`) and add an ability:

```php
// config/linkedin.php
'routes' => [
    // ...
    'middleware' => ['web', 'auth', 'can:manage-linkedin'],
],

// app/Providers/AppServiceProvider.php, in boot()
use App\Models\User;
use Illuminate\Support\Facades\Gate;

// Replace the rule with how your application recognises an administrator.
Gate::define('manage-linkedin', fn (User $user) => (bool) $user->is_admin);
```

Both routes get the same middleware. LinkedIn sends the member back in the same browser, so the session that started the flow is still signed in on the callback.

A `config/linkedin.php` that was published before 1.8 still says `['web']`, and a published value always wins over the package default. Check that file after upgrading and add `auth` yourself. A guard is the way Laravel signs a kind of user in. An application that signs its administrators in through another guard uses that guard's middleware, for example `auth:admin`.

Don't set `middleware` to `null` or `[]`. That removes every middleware, `web` included, and the routes then fail with `Session store not set on request.`

### When connecting fails

The callback reports the exception, so the details are in your log, and flashes a fixed sentence under `linkedin_error`. The text never contains LinkedIn's response body or the message of an unexpected exception:

| What happened | Flash message |
| --- | --- |
| The client ID or secret is empty | `Configure LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET first.` |
| The member or LinkedIn refused the authorization | `LinkedIn connection denied: <LinkedIn's description>`, plus a sentence about the Community Management API when a profile-only connect would work |
| The `code` is missing or the `state` does not match the session | `Invalid or expired connection session. Please try again.` |
| LinkedIn refused the code exchange | `Connecting failed: LinkedIn did not accept the authorization. Please try again.` |
| The profile could not be fetched | `Connecting failed: the LinkedIn profile could not be fetched. Please try again.` |
| LinkedIn could not be reached | `Connecting failed: LinkedIn could not be reached. Please try again.` |
| Another package exception | `Connecting failed: <the message of that exception>` |
| Anything unexpected, such as a database error | `Connecting failed because of an unexpected error. Please try again.` |

[Troubleshooting](troubleshooting.md) has the cause and the fix for each.

## Wiring the flow yourself

Set `LINKEDIN_ROUTES_ENABLED=false` and write two routes of your own. Three rules:

- Your callback route must carry the name from `linkedin.routes.callback_name` (`linkedin.callback`), because the [redirect URI](installation.md#the-redirect-uri) is the URL of that route.
- Your routes get no middleware from the package. Put `auth` and your ability on them yourself.
- Pass the scopes you asked for to **both** calls. LinkedIn may leave `scope` out of the token response; `connectFromCode()` then records its second argument as the granted scopes. Without that argument it records what the config implies at that moment, which is a guess.

```php
// routes/web.php
use Darvis\ApiLinkedin\AuthorizationDenial;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::middleware(['auth', 'can:manage-linkedin'])->group(function () {
    // Step 1: send the user to LinkedIn
    Route::get('/admin/linkedin/connect', function (LinkedInOAuth $oauth) {
        $state = Str::random(40);
        $scopes = $oauth->scopes(); // what the config implies, or Scopes::MEMBER

        session(['my_linkedin_state' => $state, 'my_linkedin_scopes' => $scopes]);

        return redirect()->away(LinkedIn::authorizationUrl($state, $scopes));
    })->name('admin.linkedin.connect');

    // Step 2: LinkedIn sends the user back here
    Route::get('/admin/linkedin/callback', function (Request $request) {
        $state = session()->pull('my_linkedin_state');
        $scopes = session()->pull('my_linkedin_scopes');

        if ($denial = AuthorizationDenial::fromCallback($request)) {
            return redirect('/admin')->with('error', 'LinkedIn refused: '.$denial->description);
        }

        abort_unless(is_string($state) && hash_equals($state, (string) $request->string('state')), 403);

        try {
            $account = LinkedIn::connectFromCode((string) $request->string('code'), $scopes);
        } catch (LinkedInException $e) {
            report($e);

            return redirect('/admin')->with('error', 'Connecting LinkedIn failed.');
        }

        return redirect('/admin')->with('status', 'Connected as '.$account->name);
    })->name('linkedin.callback');
});
```

The `state` is a random value that proves the callback belongs to the flow this browser started. Never show `$e->getMessage()` of a failed connect to the visitor; it can hold LinkedIn's raw answer.

## Apps without the Community Management API

LinkedIn refuses the **entire** authorization when a single requested scope is not authorized for your app; the member never even reaches the consent screen. An app without the Community Management API therefore cannot connect at all while the config asks for company page scopes (`organization_urn` or `organizations.enabled` is set).

Ask for the member scopes only (`openid`, `profile`, `w_member_social`). An app with only the two basic products can then connect:

```php
// With the built-in routes
$url = route('linkedin.connect', ['profile_only' => 1]);

// With your own flow: pass the same set to both calls
use Darvis\ApiLinkedin\Scopes;

$url = LinkedIn::authorizationUrl($state, Scopes::MEMBER);
$account = LinkedIn::connectFromCode($code, Scopes::MEMBER);
```

Read a refused authorization instead of parsing LinkedIn's English text, which arrives HTML-escaped and would show entities when echoed into Blade:

```php
use Darvis\ApiLinkedin\AuthorizationDenial;

if ($denial = AuthorizationDenial::fromCallback($request)) {
    $denial->description;                     // decoded, human readable
    $denial->isScopeProblem();                // your app lacks a product; retrying is pointless
    $denial->missingScope();                  // 'w_organization_social'
    $denial->isRecoverableWithMemberScopes(); // offer the profile-only connect
}
```

## The config asks, the token decides

The config says which scopes are *requested*; the stored token records which scopes LinkedIn *granted*. They drift apart the moment the LinkedIn app misses a product, or when you change `organization_urn` or `organizations.enabled` after connecting. The token always wins, so gate your UI on the connection and never on the config alone, or you offer targets that publish into a 403:

```php
use Darvis\ApiLinkedin\Scopes;

LinkedIn::isConnected();
LinkedIn::canPostAsOrganization();  // the token carries w_organization_social
LinkedIn::canListOrganizations();   // config allows it AND the token carries r_organization_admin

$account = LinkedIn::account();
$account->grantedScopes();          // ['openid', 'profile', 'w_member_social'], or null when unknown
$account->hasScope(Scopes::POST_AS_ORGANIZATION);
$account->lacksScope(Scopes::LIST_ORGANIZATIONS);
```

`hasScope()` and `lacksScope()` are not each other's negation. A connection stored before 1.4 has no recorded scopes: `grantedScopes()` is then `null` and both methods return `false`. The package refuses to guess in either direction, so nothing is offered that it cannot promise and nothing is blocked that might still work.

A token never gains scopes. When a call needs a scope the token lacks, the package throws [`LinkedInScopeMissing`](errors.md) before any request goes out, and the only fix is to add the product to the LinkedIn app and reconnect.

## Token lifecycle

Every publish first asks for a fresh access token. An access token that expires within a minute is first renewed with the refresh token, when LinkedIn issued one. LinkedIn decides whether your app gets refresh tokens; see [LinkedIn's page on refresh tokens](https://learn.microsoft.com/linkedin/shared/authentication/programmatic-refresh-tokens). A refresh leaves `updated_at` alone, because that column says when the account was connected and decides which account is the current one. When no usable refresh token is left, the package throws `LinkedInConnectionExpired`, the one failure an end user can fix by connecting again. Never read `$account->access_token` directly in your own code.
