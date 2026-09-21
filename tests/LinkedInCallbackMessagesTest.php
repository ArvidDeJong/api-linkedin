<?php

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInConnectionExpired;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
| The flash message after a failed connect is shown to whoever clicked the button.
| It may say that connecting failed, never what LinkedIn or the application said.
*/

/**
 * Collects what reaches report(), and keeps it out of the test log.
 *
 * @return ArrayObject<int, Throwable>
 */
function reportedExceptions(): ArrayObject
{
    /** @var ArrayObject<int, Throwable> $reported */
    $reported = new ArrayObject;

    app(ExceptionHandler::class)->reportable(function (Throwable $e) use ($reported): bool {
        $reported->append($e);

        return false;
    });

    return $reported;
}

function callbackAsUser(): TestResponse
{
    return test()->actingAs(new User)
        ->withSession(['linkedin_oauth_state' => 'state-xyz'])
        ->get(route('linkedin.callback', ['code' => 'code', 'state' => 'state-xyz']));
}

it('does not flash the raw LinkedIn response when the code exchange fails', function () {
    $reported = reportedExceptions();

    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'error' => 'invalid_request',
            'error_description' => 'internal-detail-from-linkedin',
        ], 400),
    ]);

    callbackAsUser()->assertSessionHas('linkedin_error');

    expect(session('linkedin_error'))
        ->toStartWith('Connecting failed')
        ->not->toContain('internal-detail-from-linkedin')
        ->not->toContain('invalid_request');

    expect($reported)->toHaveCount(1)
        ->and($reported[0])->toBeInstanceOf(LinkedInApiException::class)
        ->and($reported[0]->body)->toContain('internal-detail-from-linkedin');
});

it('flashes a fixed message and reports the exception on an unexpected error', function () {
    $reported = reportedExceptions();

    $this->mock(LinkedInOAuth::class, function ($mock) {
        $mock->shouldReceive('connectFromCode')
            ->andThrow(new RuntimeException('SQLSTATE[HY000]: secret-table-name does not exist'));
    });

    callbackAsUser()->assertSessionHas('linkedin_error');

    expect(session('linkedin_error'))
        ->toBe('Connecting failed because of an unexpected error. Please try again.');

    expect($reported)->toHaveCount(1)
        ->and($reported[0])->toBeInstanceOf(RuntimeException::class);
});

it('says that LinkedIn could not be reached without naming the address', function () {
    reportedExceptions();

    Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://www.linkedin.com/oauth/v2/accessToken'));

    callbackAsUser()->assertSessionHas('linkedin_error', 'Connecting failed: LinkedIn could not be reached. Please try again.');
});

it('keeps the wording of the package exceptions that are written for the user', function () {
    reportedExceptions();

    $this->mock(LinkedInOAuth::class, function ($mock) {
        $mock->shouldReceive('connectFromCode')->andThrow(new LinkedInConnectionExpired);
    });

    callbackAsUser()->assertSessionHas(
        'linkedin_error',
        'Connecting failed: The LinkedIn connection has expired. Please reconnect the account.',
    );
});
