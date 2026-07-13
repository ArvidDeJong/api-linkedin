<?php

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;
use Illuminate\Support\Facades\Http;

function makeAccount(): LinkedInAccount
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

it('publishes on behalf of the member and returns the urn and permalink', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:999']),
    ]);

    $account = makeAccount();

    $result = app(LinkedInPublisher::class)->publish($account, $account->member_urn, 'Hello world');

    expect($result['urn'])->toBe('urn:li:share:999')
        ->and($result['permalink'])->toBe('https://www.linkedin.com/feed/update/urn:li:share:999/');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.linkedin.com/rest/posts'
        && $request['author'] === 'urn:li:person:12345'
        && $request->hasHeader('LinkedIn-Version', '202601'));
});

it('escapes reserved characters in the commentary', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:1']),
    ]);

    $account = makeAccount();

    app(LinkedInPublisher::class)->publish($account, $account->member_urn, 'Price (excl. VAT) #offer');

    Http::assertSent(fn ($request) => str_contains($request['commentary'], '\\(excl. VAT\\)')
        && str_contains($request['commentary'], '\\#offer'));
});

it('throws on an API error', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Broken'], 422),
    ]);

    $account = makeAccount();

    app(LinkedInPublisher::class)->publish($account, $account->member_urn, 'Text');
})->throws(LinkedInException::class);

it('posts on behalf of the company page through the facade using the organization urn', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:2']),
    ]);

    makeAccount();

    LinkedIn::postAsOrganization('Company news');

    Http::assertSent(fn ($request) => $request['author'] === 'urn:li:organization:42');
});

it('throws when publishing without a connected account', function () {
    LinkedIn::postAsMember('Text');
})->throws(LinkedInException::class);
