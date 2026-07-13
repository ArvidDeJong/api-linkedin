<?php

use Darvis\ApiLinkedin\AuthorizationDenial;
use Darvis\ApiLinkedin\Exceptions\LinkedInScopeMissing;
use Darvis\ApiLinkedin\LinkedInManager;
use Darvis\ApiLinkedin\Scopes;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Darvis\ApiLinkedin\Services\LinkedInOrganizations;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** The scopes a LinkedIn app without the Community Management API can get. */
const MEMBER_ONLY = ['scopes' => 'openid profile w_member_social'];

it('knows which scopes it was granted', function () {
    $account = account(MEMBER_ONLY);

    expect($account->grantedScopes())->toBe(['openid', 'profile', 'w_member_social'])
        ->and($account->hasScope(Scopes::POST_AS_MEMBER))->toBeTrue()
        ->and($account->canPostAsOrganization())->toBeFalse()
        ->and($account->canListOrganizations())->toBeFalse()
        ->and($account->lacksScope(Scopes::POST_AS_ORGANIZATION))->toBeTrue();
});

it('claims nothing either way when the granted scopes are unknown', function () {
    $account = account();

    // Neither method is the other's negation: an account from before 1.4 simply
    // does not tell us, and guessing in either direction would be wrong.
    expect($account->knowsScopes())->toBeFalse()
        ->and($account->hasScope(Scopes::POST_AS_ORGANIZATION))->toBeFalse()
        ->and($account->lacksScope(Scopes::POST_AS_ORGANIZATION))->toBeFalse();
});

it('builds an authorization URL for the member scopes only', function () {
    $url = app(LinkedInOAuth::class)->authorizationUrl('state-123', Scopes::MEMBER);

    expect($url)
        ->toContain('w_member_social')
        ->not->toContain('w_organization_social')
        ->not->toContain('r_organization_admin');
});

it('records the scopes it asked for when LinkedIn does not return them', function () {
    Http::fake([
        // No `scope` in the response — the config still asks for a company page.
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'token',
            'expires_in' => 5184000,
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response(['sub' => '9', 'name' => 'Member']),
    ]);

    $account = app(LinkedInOAuth::class)->connectFromCode('code', Scopes::MEMBER);

    expect($account->scopes)->toBe('openid profile w_member_social')
        ->and($account->canPostAsOrganization())->toBeFalse();
});

it('prefers the scopes LinkedIn granted over the ones requested', function () {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'token',
            'expires_in' => 5184000,
            'scope' => 'openid profile w_member_social',
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response(['sub' => '9', 'name' => 'Member']),
    ]);

    $account = app(LinkedInOAuth::class)->connectFromCode('code', [Scopes::POST_AS_ORGANIZATION]);

    expect($account->grantedScopes())->toBe(['openid', 'profile', 'w_member_social']);
});

it('refuses to post as a company page without the scope', function () {
    Http::fake();

    app(LinkedInPublisher::class)->publish(
        account(MEMBER_ONLY),
        'urn:li:organization:42',
        'Text',
    );

    Http::assertNothingSent();
})->throws(LinkedInScopeMissing::class);

it('still posts on the member profile without the organization scope', function () {
    Http::fake(['https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:1'])]);

    $result = app(LinkedInPublisher::class)->publish(
        account(MEMBER_ONLY),
        'urn:li:person:1',
        'Text',
    );

    expect($result['urn'])->toBe('urn:li:share:1');
});

it('lets a connection with unknown scopes try anyway', function () {
    Http::fake(['https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:1'])]);

    // Refusing here would break every account stored before 1.4.
    $result = app(LinkedInPublisher::class)->publish(account(), 'urn:li:organization:42', 'Text');

    expect($result['urn'])->toBe('urn:li:share:1');
});

it('refuses to list company pages without the scope', function () {
    Http::fake();
    config()->set('linkedin.organizations.cache_ttl', 0);

    app(LinkedInOrganizations::class)->all(account(MEMBER_ONLY));

    Http::assertNothingSent();
})->throws(LinkedInScopeMissing::class);

it('names the missing product in the exception', function () {
    $exception = new LinkedInScopeMissing(Scopes::LIST_ORGANIZATIONS);

    expect($exception->scope)->toBe('r_organization_admin')
        ->and($exception->getMessage())->toContain('Community Management API');
});

it('answers what the connection may do', function () {
    config()->set('linkedin.organizations.enabled', true);
    account(MEMBER_ONLY);

    $linkedin = app(LinkedInManager::class);

    // The config says yes, the token says no — the token wins.
    expect($linkedin->organizationListingEnabled())->toBeTrue()
        ->and($linkedin->canListOrganizations())->toBeFalse()
        ->and($linkedin->canPostAsOrganization())->toBeFalse();
});

it('reads a denied authorization from the callback', function () {
    $denial = AuthorizationDenial::fromCallback(Request::create('/callback', 'GET', [
        'error' => 'unauthorized_scope_error',
        // LinkedIn sends the description HTML-escaped.
        'error_description' => 'Scope &quot;w_organization_social&quot; is not authorized for your application',
    ]));

    expect($denial->error)->toBe('unauthorized_scope_error')
        ->and($denial->description)->toContain('"w_organization_social"')
        ->and($denial->description)->not->toContain('&quot;')
        ->and($denial->isScopeProblem())->toBeTrue()
        ->and($denial->missingScope())->toBe('w_organization_social')
        ->and($denial->isRecoverableWithMemberScopes())->toBeTrue();
});

it('does not offer a member-only retry when the member simply declined', function () {
    $denial = AuthorizationDenial::fromCallback(Request::create('/callback', 'GET', [
        'error' => 'user_cancelled_login',
        'error_description' => 'The user cancelled the login',
    ]));

    expect($denial->isScopeProblem())->toBeFalse()
        ->and($denial->isRecoverableWithMemberScopes())->toBeFalse();
});

it('sees no denial on a successful callback', function () {
    expect(AuthorizationDenial::fromCallback(Request::create('/callback', 'GET', ['code' => 'abc'])))->toBeNull();
});
