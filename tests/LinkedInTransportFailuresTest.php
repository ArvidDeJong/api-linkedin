<?php

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
| The docs promise that every failure is a LinkedInException. That has to hold
| when LinkedIn cannot be reached at all and when it answers with something that
| is not the JSON we expect.
*/

function linkedInIsUnreachable(): void
{
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out for https://upload.example/secret-signed-url'));
}

/**
 * @param  Closure(): mixed  $call
 */
function expectUnreachable(Closure $call, string $operation): void
{
    try {
        $call();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LinkedInApiException::class);
        /** @var LinkedInApiException $e */
        expect($e->operation)->toBe($operation)
            ->and($e->status)->toBe(0)
            ->and($e->body)->toBe('')
            ->and($e->isConnectionProblem())->toBeTrue()
            ->and($e->isAuthorizationProblem())->toBeFalse()
            ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class)
            ->and($e->getMessage())->not->toContain('secret-signed-url');

        return;
    }

    test()->fail('No exception was thrown.');
}

it('wraps a connection failure during the code exchange', function () {
    linkedInIsUnreachable();

    expectUnreachable(fn () => app(LinkedInOAuth::class)->connectFromCode('code'), LinkedInApiException::OPERATION_TOKEN);
});

it('wraps a connection failure while fetching the profile', function () {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'token']),
        'https://api.linkedin.com/v2/userinfo' => fn () => throw new ConnectionException('cURL error 28'),
    ]);

    expectUnreachable(fn () => app(LinkedInOAuth::class)->connectFromCode('code'), LinkedInApiException::OPERATION_PROFILE);
});

it('wraps a connection failure while refreshing the token', function () {
    linkedInIsUnreachable();
    $account = account(['token_expires_at' => now()->subDay()]);

    expectUnreachable(fn () => app(LinkedInOAuth::class)->freshAccessToken($account), LinkedInApiException::OPERATION_TOKEN);
});

it('wraps a connection failure while publishing', function () {
    linkedInIsUnreachable();
    account();

    expectUnreachable(fn () => LinkedIn::postAsMember('Hello'), LinkedInApiException::OPERATION_PUBLISH);
});

it('wraps a connection failure while listing company pages', function () {
    linkedInIsUnreachable();
    account(['scopes' => 'openid profile w_member_social r_organization_admin']);

    expectUnreachable(fn () => LinkedIn::organizations(), LinkedInApiException::OPERATION_ORGANIZATIONS);
});

it('wraps a connection failure while initializing an image upload', function () {
    linkedInIsUnreachable();
    account();

    expectUnreachable(fn () => LinkedIn::uploadImage('urn:li:person:1', 'bytes'), LinkedInApiException::OPERATION_IMAGE);
});

it('wraps a connection failure while uploading the image bytes', function () {
    Http::fake([
        'https://api.linkedin.com/rest/images*' => Http::response(['value' => [
            'uploadUrl' => 'https://upload.example/secret-signed-url',
            'image' => 'urn:li:image:1',
        ]]),
        'https://upload.example/*' => fn () => throw new ConnectionException('cURL error 28 for https://upload.example/secret-signed-url'),
    ]);
    account();

    expectUnreachable(fn () => LinkedIn::uploadImage('urn:li:person:1', 'bytes'), LinkedInApiException::OPERATION_IMAGE);
});

it('throws the package exception on a token response that is not JSON', function (string $body) {
    Http::fake(['https://www.linkedin.com/oauth/v2/accessToken' => Http::response($body, 200)]);

    expect(fn () => app(LinkedInOAuth::class)->connectFromCode('code'))
        ->toThrow(LinkedInApiException::class, 'access token');

    expect(LinkedInAccount::count())->toBe(0);
})->with([
    'empty body' => [''],
    'html' => ['<html>Service unavailable</html>'],
    'json without access_token' => ['{"expires_in":5184000}'],
    'json scalar' => ['"ok"'],
    'empty access_token' => ['{"access_token":""}'],
]);

it('throws the package exception on a refresh response without an access token', function () {
    Http::fake(['https://www.linkedin.com/oauth/v2/accessToken' => Http::response('', 200)]);
    $account = account(['token_expires_at' => now()->subDay()]);

    expect(fn () => app(LinkedInOAuth::class)->freshAccessToken($account))->toThrow(LinkedInException::class);

    expect($account->fresh()?->access_token)->toBe('token');
});

it('throws the package exception on a profile response without a member id', function (string $body) {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'token']),
        'https://api.linkedin.com/v2/userinfo' => Http::response($body, 200),
    ]);

    try {
        app(LinkedInOAuth::class)->connectFromCode('code');
        $this->fail('No exception was thrown.');
    } catch (LinkedInApiException $e) {
        expect($e->operation)->toBe(LinkedInApiException::OPERATION_PROFILE)
            ->and($e->status)->toBe(200);
    }

    expect(LinkedInAccount::count())->toBe(0);
})->with([
    'empty body' => [''],
    'html' => ['<html></html>'],
    'json without sub' => ['{"name":"Someone"}'],
    'empty sub' => ['{"sub":""}'],
]);

it('sends every HTTP call in the services through the transport', function () {
    foreach (glob(__DIR__.'/../src/Services/*.php') ?: [] as $file) {
        $source = (string) file_get_contents($file);

        expect(substr_count($source, 'Http::'))
            ->toBe(substr_count($source, 'Transport::send('), basename($file).' calls Http:: outside Transport::send()');
    }
});
