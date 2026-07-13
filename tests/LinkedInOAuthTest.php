<?php

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Support\Facades\Http;

it('bevat de standaardscopes plus de organisatie-scope', function () {
    $scopes = app(LinkedInOAuth::class)->scopes();

    expect($scopes)->toContain('openid', 'profile', 'w_member_social', 'w_organization_social');
});

it('vraagt de organisatie-scope niet aan zonder bedrijfspagina', function () {
    config()->set('linkedin.organization_urn', null);

    expect(app(LinkedInOAuth::class)->scopes())->not->toContain('w_organization_social');
});

it('bouwt een autorisatie-URL met state, redirect en scopes', function () {
    $url = app(LinkedInOAuth::class)->authorizationUrl('state-123');

    expect($url)
        ->toContain('https://www.linkedin.com/oauth/v2/authorization')
        ->toContain('client_id=client-id')
        ->toContain('state=state-123')
        ->toContain('scope=openid');
});

it('wisselt een code in en slaat de verbinding op', function () {
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

it('vernieuwt een verlopen token met het refresh-token', function () {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'nieuw-token',
            'expires_in' => 5184000,
        ]),
    ]);

    $account = LinkedInAccount::create([
        'member_id' => '1',
        'member_urn' => 'urn:li:person:1',
        'name' => 'Test',
        'access_token' => 'oud-token',
        'refresh_token' => 'refresh',
        'token_expires_at' => now()->subDay(),
        'refresh_token_expires_at' => now()->addYear(),
    ]);

    $token = app(LinkedInOAuth::class)->freshAccessToken($account);

    expect($token)->toBe('nieuw-token')
        ->and($account->fresh()->access_token)->toBe('nieuw-token');
});

it('werpt een fout wanneer een verlopen token niet te vernieuwen is', function () {
    $account = LinkedInAccount::create([
        'member_id' => '1',
        'member_urn' => 'urn:li:person:1',
        'name' => 'Test',
        'access_token' => 'oud-token',
        'refresh_token' => null,
        'token_expires_at' => now()->subDay(),
    ]);

    app(LinkedInOAuth::class)->freshAccessToken($account);
})->throws(LinkedInException::class);
