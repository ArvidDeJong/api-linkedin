<?php

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Support\Facades\Http;

it('contains the default scopes plus the organization scope', function () {
    $scopes = app(LinkedInOAuth::class)->scopes();

    expect($scopes)->toContain('openid', 'profile', 'w_member_social', 'w_organization_social');
});

it('does not request the organization scope without a company page', function () {
    config()->set('linkedin.organization_urn', null);

    expect(app(LinkedInOAuth::class)->scopes())->not->toContain('w_organization_social');
});

it('builds an authorization URL with state, redirect and scopes', function () {
    $url = app(LinkedInOAuth::class)->authorizationUrl('state-123');

    expect($url)
        ->toContain('https://www.linkedin.com/oauth/v2/authorization')
        ->toContain('client_id=client-id')
        ->toContain('state=state-123')
        ->toContain('scope=openid');
});

it('exchanges a code and stores the connection', function () {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'the-access-token',
            'expires_in' => 5184000,
            'refresh_token' => 'the-refresh-token',
            'refresh_token_expires_in' => 31536000,
            'scope' => 'openid profile w_member_social',
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response([
            'sub' => '98765',
            'name' => 'Arvid de Jong',
        ]),
    ]);

    $account = app(LinkedInOAuth::class)->connectFromCode('auth-code');

    expect($account->member_urn)->toBe('urn:li:person:98765')
        ->and($account->name)->toBe('Arvid de Jong')
        ->and($account->access_token)->toBe('the-access-token')
        ->and(LinkedInAccount::current()->id)->toBe($account->id);
});

it('refreshes an expired token with the refresh token', function () {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'new-token',
            'expires_in' => 5184000,
        ]),
    ]);

    $account = LinkedInAccount::create([
        'member_id' => '1',
        'member_urn' => 'urn:li:person:1',
        'name' => 'Test',
        'access_token' => 'old-token',
        'refresh_token' => 'refresh',
        'token_expires_at' => now()->subDay(),
        'refresh_token_expires_at' => now()->addYear(),
    ]);

    $token = app(LinkedInOAuth::class)->freshAccessToken($account);

    expect($token)->toBe('new-token')
        ->and($account->fresh()->access_token)->toBe('new-token');
});

it('throws when an expired token cannot be refreshed', function () {
    $account = LinkedInAccount::create([
        'member_id' => '1',
        'member_urn' => 'urn:li:person:1',
        'name' => 'Test',
        'access_token' => 'old-token',
        'refresh_token' => null,
        'token_expires_at' => now()->subDay(),
    ]);

    app(LinkedInOAuth::class)->freshAccessToken($account);
})->throws(LinkedInException::class);
