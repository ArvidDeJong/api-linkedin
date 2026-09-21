<?php

use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| "Current" is the account that was connected last, also when that member already
| had a row from an earlier connect.
*/

function fakeConnectAs(string $memberId, string $name, string $token = 'token'): void
{
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => $token,
            'expires_in' => 5184000,
            'scope' => 'openid profile w_member_social r_organization_admin',
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response(['sub' => $memberId, 'name' => $name]),
    ]);
}

it('makes a reconnected member the current account again', function () {
    $first = account(['member_id' => 'first', 'member_urn' => 'urn:li:person:first', 'name' => 'First']);

    $this->travel(1)->hours();
    account(['member_id' => 'second', 'member_urn' => 'urn:li:person:second', 'name' => 'Second']);

    $this->travel(1)->hours();
    fakeConnectAs('first', 'First', 'new-token');
    app(LinkedInOAuth::class)->connectFromCode('code');

    expect(LinkedInAccount::count())->toBe(2)
        ->and(LinkedInAccount::current()?->id)->toBe($first->id);
});

it('makes a reconnected member current even when nothing in the row changed', function () {
    fakeConnectAs('first', 'First');
    $first = app(LinkedInOAuth::class)->connectFromCode('code');

    // Freeze the clock so the expiry timestamps of the second connect are identical.
    $this->travelTo(now()->startOfSecond()->addHour());
    account(['member_id' => 'second', 'member_urn' => 'urn:li:person:second', 'name' => 'Second']);

    $frozen = now()->addHour();
    $this->travelTo($frozen);
    $first->forceFill(['token_expires_at' => $frozen->copy()->addSeconds(5184000)])->saveQuietly();
    LinkedInAccount::query()->whereKey($first->id)->update(['updated_at' => $frozen->copy()->subHours(2)]);

    app(LinkedInOAuth::class)->connectFromCode('code');

    expect(LinkedInAccount::current()?->id)->toBe($first->id);
});

it('keeps the newest row current when two accounts were stored in the same second', function () {
    $this->freezeSecond();

    account(['member_id' => 'first', 'member_urn' => 'urn:li:person:first']);
    $second = account(['member_id' => 'second', 'member_urn' => 'urn:li:person:second']);

    expect(LinkedInAccount::current()?->id)->toBe($second->id);
});

it('does not make an account current by refreshing its token', function () {
    $first = account([
        'member_id' => 'first',
        'member_urn' => 'urn:li:person:first',
        'token_expires_at' => now()->subDay(),
    ]);

    $this->travel(1)->hours();
    $second = account(['member_id' => 'second', 'member_urn' => 'urn:li:person:second']);

    $this->travel(1)->hours();
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'refreshed', 'expires_in' => 5184000]),
    ]);

    expect(app(LinkedInOAuth::class)->freshAccessToken($first))->toBe('refreshed')
        ->and(LinkedInAccount::current()?->id)->toBe($second->id);
});

it('drops the cached company pages when the account reconnects', function () {
    $account = account(['member_id' => 'first', 'scopes' => 'openid profile w_member_social r_organization_admin']);

    Cache::put('linkedin.organizations.'.$account->id, [['urn' => 'urn:li:organization:1']], 3600);

    fakeConnectAs('first', 'First');
    app(LinkedInOAuth::class)->connectFromCode('code');

    expect(Cache::has('linkedin.organizations.'.$account->id))->toBeFalse();
});

it('drops the cached company pages on disconnect', function () {
    $first = account(['member_id' => 'first']);
    $second = account(['member_id' => 'second', 'member_urn' => 'urn:li:person:second']);

    Cache::put('linkedin.organizations.'.$first->id, [['urn' => 'urn:li:organization:1']], 3600);
    Cache::put('linkedin.organizations.'.$second->id, [['urn' => 'urn:li:organization:2']], 3600);

    LinkedIn::disconnect();

    expect(LinkedInAccount::count())->toBe(0)
        ->and(Cache::has('linkedin.organizations.'.$first->id))->toBeFalse()
        ->and(Cache::has('linkedin.organizations.'.$second->id))->toBeFalse();
});
