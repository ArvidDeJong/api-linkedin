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

it('publiceert namens het lid en geeft urn en permalink terug', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:999']),
    ]);

    $account = makeAccount();

    $result = app(LinkedInPublisher::class)->publish($account, $account->member_urn, 'Hallo wereld');

    expect($result['urn'])->toBe('urn:li:share:999')
        ->and($result['permalink'])->toBe('https://www.linkedin.com/feed/update/urn:li:share:999/');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.linkedin.com/rest/posts'
        && $request['author'] === 'urn:li:person:12345'
        && $request->hasHeader('LinkedIn-Version', '202601'));
});

it('escapet gereserveerde tekens in de begeleidende tekst', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:1']),
    ]);

    $account = makeAccount();

    app(LinkedInPublisher::class)->publish($account, $account->member_urn, 'Prijs (excl. btw) #aanbieding');

    Http::assertSent(fn ($request) => str_contains($request['commentary'], '\\(excl. btw\\)')
        && str_contains($request['commentary'], '\\#aanbieding'));
});

it('werpt een fout bij een API-fout', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Kapot'], 422),
    ]);

    $account = makeAccount();

    app(LinkedInPublisher::class)->publish($account, $account->member_urn, 'Tekst');
})->throws(LinkedInException::class);

it('post via de facade namens de bedrijfspagina met de organisatie-urn', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:2']),
    ]);

    makeAccount();

    LinkedIn::postAsOrganization('Bedrijfsnieuws');

    Http::assertSent(fn ($request) => $request['author'] === 'urn:li:organization:42');
});

it('werpt een fout bij posten zonder gekoppeld account', function () {
    LinkedIn::postAsMember('Tekst');
})->throws(LinkedInException::class);
