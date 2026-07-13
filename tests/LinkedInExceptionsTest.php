<?php

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInConfigurationException;
use Darvis\ApiLinkedin\Exceptions\LinkedInConnectionExpired;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Exceptions\LinkedInNotConnected;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Support\Facades\Http;

function account(array $attributes = []): LinkedInAccount
{
    return LinkedInAccount::create(array_merge([
        'member_id' => '1',
        'member_urn' => 'urn:li:person:1',
        'name' => 'Tester',
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'token_expires_at' => now()->addDays(30),
        'refresh_token_expires_at' => now()->addYear(),
    ], $attributes));
}

it('throws LinkedInNotConnected when nothing is connected', function () {
    LinkedIn::postAsMember('Text');
})->throws(LinkedInNotConnected::class);

it('throws LinkedInConnectionExpired when the token cannot be refreshed', function () {
    $expired = account(['refresh_token' => null, 'token_expires_at' => now()->subDay()]);

    app(LinkedInOAuth::class)->freshAccessToken($expired);
})->throws(LinkedInConnectionExpired::class);

it('throws LinkedInConfigurationException without a company page', function () {
    config()->set('linkedin.organization_urn', null);
    account();

    LinkedIn::postAsOrganization('Text');
})->throws(LinkedInConfigurationException::class);

it('throws LinkedInApiException with the operation, status and body when publishing fails', function () {
    Http::fake([
        'https://api.linkedin.com/rest/posts' => Http::response(['message' => 'Broken'], 422),
    ]);

    account();

    try {
        LinkedIn::postAsMember('Text');
    } catch (LinkedInApiException $e) {
        expect($e->operation)->toBe(LinkedInApiException::OPERATION_PUBLISH)
            ->and($e->status)->toBe(422)
            ->and($e->body)->toContain('Broken')
            ->and($e->isAuthorizationProblem())->toBeFalse();

        return;
    }

    $this->fail('No LinkedInApiException was thrown.');
});

it('marks a 403 on the company pages as an authorization problem', function () {
    config()->set('linkedin.organizations.enabled', true);

    Http::fake([
        'https://api.linkedin.com/rest/organizationAcls*' => Http::response(['message' => 'Not enough permissions'], 403),
    ]);

    account();

    try {
        LinkedIn::organizations();
    } catch (LinkedInApiException $e) {
        expect($e->operation)->toBe(LinkedInApiException::OPERATION_ORGANIZATIONS)
            ->and($e->status)->toBe(403)
            ->and($e->isAuthorizationProblem())->toBeTrue();

        return;
    }

    $this->fail('No LinkedInApiException was thrown.');
});

it('keeps a not-connected error catchable as the base LinkedInException', function () {
    LinkedIn::postAsMember('Text');
})->throws(LinkedInException::class);

it('keeps an expired connection catchable as the base LinkedInException', function () {
    $expired = account(['refresh_token' => null, 'token_expires_at' => now()->subDay()]);

    app(LinkedInOAuth::class)->freshAccessToken($expired);
})->throws(LinkedInException::class);
