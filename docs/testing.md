---
title: "Testing"
description: "Test Laravel code that uses darvis/api-linkedin without calling LinkedIn: fake the HTTP calls, create a connected account, and assert on the post that was sent."
nav_order: 9
---

# Testing

Every call the package makes goes through Laravel's HTTP client. `Http::fake()` therefore replaces all of them, and no test has to reach LinkedIn. `Http::preventStrayRequests()` makes a test fail when a call slips past your fakes.

## A complete example

This test covers the job from the [quick start](quickstart.md#3-publish-from-a-queued-job). It stores a connected account, fakes the answer of the Posts API, runs the job and checks what was sent.

```php
// tests/Feature/PublishToLinkedInTest.php
namespace Tests\Feature;

use App\Jobs\PublishToLinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishToLinkedInTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_the_text_on_the_member_profile(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'api.linkedin.com/rest/posts' => Http::response('', 201, [
                'x-restli-id' => 'urn:li:share:123',
            ]),
        ]);

        LinkedInAccount::create([
            'member_id' => 'abc123',
            'member_urn' => 'urn:li:person:abc123',
            'name' => 'Test Member',
            'access_token' => 'fake-access-token',
            'scopes' => 'openid profile w_member_social',
            'token_expires_at' => now()->addDays(30),
        ]);

        (new PublishToLinkedIn('Hello LinkedIn'))->handle();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.linkedin.com/rest/posts'
            && $request['author'] === 'urn:li:person:abc123'
            && $request['commentary'] === 'Hello LinkedIn');
    }
}
```

`RefreshDatabase` runs the package migration too, so the `linkedin_accounts` table exists. The account has a token that is valid for thirty days, so the package does not try to refresh it. The post id comes from the `x-restli-id` response header, not from the body: leave the header out of the fake and `urn` and `permalink` come back empty.

The package escapes reserved characters in the text. A text with a `#` arrives as `\#`, so assert on the escaped form.

## The addresses to fake

| Call | Address | A successful answer |
| --- | --- | --- |
| Code exchange and token refresh | `www.linkedin.com/oauth/v2/accessToken` | JSON with `access_token`, and optionally `expires_in`, `refresh_token`, `refresh_token_expires_in`, `scope` |
| Member profile during a connect | `api.linkedin.com/v2/userinfo` | JSON with `sub` and `name` |
| Publishing a post | `api.linkedin.com/rest/posts` | Status 201 with the header `x-restli-id` |
| Listing company pages | `api.linkedin.com/rest/organizationAcls*` | JSON with `elements`, each with an `organization` URN |
| Starting an image upload | `api.linkedin.com/rest/images*` | JSON with `value.uploadUrl` and `value.image` |
| Sending the image bytes | the `uploadUrl` from the previous answer | Any 2xx status |

## Testing a failure

Fake an error status to get a `LinkedInApiException`, or throw Laravel's `ConnectionException` to simulate an unreachable LinkedIn:

```php
use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// LinkedIn says no: $e->status is 422 and $e->body holds the JSON below
Http::fake([
    'api.linkedin.com/rest/posts' => Http::response(['message' => 'Duplicate'], 422),
]);

// LinkedIn cannot be reached: $e->status is 0 and $e->isConnectionProblem() is true
Http::fake(fn () => throw new ConnectionException('Connection timed out'));

try {
    LinkedIn::postAsMember('Hello');
} catch (LinkedInApiException $e) {
    // assert on $e->operation, $e->status, $e->isConnectionProblem()
}
```

Use one of the two fakes per test. Without a stored account every facade method throws `LinkedInNotConnected` before any call, so a test of that case needs no fake at all.

## Testing your own connect flow

The built-in routes need a signed-in user. In a feature test, sign in with `actingAs()` and put the `state` in the session:

```php
use App\Models\User;
use Illuminate\Support\Facades\Http;

Http::fake([
    'www.linkedin.com/oauth/v2/accessToken' => Http::response([
        'access_token' => 'fake-access-token',
        'expires_in' => 5184000,
        'scope' => 'openid profile w_member_social',
    ]),
    'api.linkedin.com/v2/userinfo' => Http::response(['sub' => 'abc123', 'name' => 'Test Member']),
]);

$this->actingAs(User::factory()->create())
    ->withSession(['linkedin_oauth_state' => 'known-state'])
    ->get(route('linkedin.callback', ['code' => 'any-code', 'state' => 'known-state']))
    ->assertSessionHas('linkedin_status', 'LinkedIn connected as Test Member.');
```

When your config adds an ability such as `can:manage-linkedin`, the user in the test has to pass that gate.
