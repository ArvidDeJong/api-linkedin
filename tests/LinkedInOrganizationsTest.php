<?php

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Support\Facades\Http;

function connectAccount(): LinkedInAccount
{
    return LinkedInAccount::create([
        'member_id' => '12345',
        'member_urn' => 'urn:li:person:12345',
        'name' => 'Arvid de Jong',
        'access_token' => 'valid-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addDays(30),
        'refresh_token_expires_at' => now()->addYear(),
    ]);
}

function fakeAcls(): void
{
    Http::fake([
        'https://api.linkedin.com/rest/organizationAcls*' => Http::response([
            'elements' => [
                [
                    'organization' => 'urn:li:organization:42',
                    'role' => 'ADMINISTRATOR',
                    'organization~' => [
                        'id' => 42,
                        'localizedName' => 'Darvis Websites & Apps',
                        'vanityName' => 'darvis',
                    ],
                ],
                [
                    'organization' => 'urn:li:organization:99',
                    'role' => 'ADMINISTRATOR',
                    'organization~' => [
                        'id' => 99,
                        'localizedName' => 'Tweede Bedrijf',
                        'vanityName' => 'tweede',
                    ],
                ],
            ],
        ]),
    ]);
}

beforeEach(function () {
    config()->set('linkedin.organizations.enabled', true);
});

it('requests the admin scope only when organization listing is enabled', function () {
    expect(app(LinkedInOAuth::class)->scopes())->toContain('r_organization_admin', 'w_organization_social');

    config()->set('linkedin.organizations.enabled', false);
    config()->set('linkedin.organization_urn', null);

    expect(app(LinkedInOAuth::class)->scopes())
        ->not->toContain('r_organization_admin')
        ->not->toContain('w_organization_social');
});

it('requests organization scopes when listing is on but no default page is configured', function () {
    config()->set('linkedin.organization_urn', null);

    expect(app(LinkedInOAuth::class)->scopes())->toContain('r_organization_admin', 'w_organization_social');
});

it('lists the company pages the member administers', function () {
    fakeAcls();
    connectAccount();

    expect(LinkedIn::organizations())->toBe([
        ['urn' => 'urn:li:organization:42', 'id' => '42', 'name' => 'Darvis Websites & Apps', 'vanity_name' => 'darvis'],
        ['urn' => 'urn:li:organization:99', 'id' => '99', 'name' => 'Tweede Bedrijf', 'vanity_name' => 'tweede'],
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'organizationAcls')
        && str_contains($request->url(), 'roleAssignee')
        && str_contains($request->url(), 'ADMINISTRATOR'));
});

it('falls back to the urn when the decorated organization is missing', function () {
    Http::fake([
        'https://api.linkedin.com/rest/organizationAcls*' => Http::response([
            'elements' => [['organization' => 'urn:li:organization:7', 'role' => 'ADMINISTRATOR']],
        ]),
    ]);

    connectAccount();

    expect(LinkedIn::organizations())->toBe([
        ['urn' => 'urn:li:organization:7', 'id' => '7', 'name' => 'urn:li:organization:7', 'vanity_name' => null],
    ]);
});

it('caches the list and refetches when asked for fresh data', function () {
    fakeAcls();
    connectAccount();

    LinkedIn::organizations();
    LinkedIn::organizations();

    Http::assertSentCount(1);

    LinkedIn::organizations(fresh: true);

    Http::assertSentCount(2);
});

it('does not cache when the ttl is zero', function () {
    config()->set('linkedin.organizations.cache_ttl', 0);
    fakeAcls();
    connectAccount();

    LinkedIn::organizations();
    LinkedIn::organizations();

    Http::assertSentCount(2);
});

it('throws when LinkedIn refuses the acl request', function () {
    Http::fake([
        'https://api.linkedin.com/rest/organizationAcls*' => Http::response(['message' => 'Not enough permissions'], 403),
    ]);

    connectAccount();

    LinkedIn::organizations();
})->throws(LinkedInException::class);

it('throws when listing without a connected account', function () {
    LinkedIn::organizations();
})->throws(LinkedInException::class, 'No active LinkedIn connection.');

it('posts to a specific company page when an urn is given', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:5']),
    ]);

    connectAccount();

    LinkedIn::postAsOrganization('Nieuws', 'urn:li:organization:99');

    Http::assertSent(fn ($request) => $request['author'] === 'urn:li:organization:99');
});

it('falls back to the configured page when no urn is given', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:6']),
    ]);

    connectAccount();

    LinkedIn::postAsOrganization('Nieuws');

    Http::assertSent(fn ($request) => $request['author'] === 'urn:li:organization:42');
});
