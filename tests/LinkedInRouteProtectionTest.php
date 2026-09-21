<?php

use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Support\LinkedInConfig;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
| Whoever completes the connect flow becomes the connection the whole application
| posts with, so the built-in routes must not be reachable for a guest with the
| package defaults.
*/

beforeEach(function () {
    // The Testbench app has no login route; the auth middleware redirects a guest to it.
    Route::get('login', fn () => 'login')->name('login');
});

it('requires a signed-in user by default', function () {
    expect(LinkedInConfig::routeMiddleware())->toBe(['web', 'auth']);

    // A published config from before the key existed falls back to the same default.
    config()->set('linkedin.routes', ['enabled' => true]);

    expect(LinkedInConfig::routeMiddleware())->toBe(['web', 'auth']);
});

it('sends a guest away from the connect route', function () {
    $response = $this->get(route('linkedin.connect'));

    $response->assertRedirect(route('login'));

    expect(session('linkedin_oauth_state'))->toBeNull();
});

it('answers a guest that expects JSON on the connect route with a 401', function () {
    $this->getJson(route('linkedin.connect'))->assertUnauthorized();
});

it('does not let a guest complete the callback', function () {
    Http::fake();

    $this->withSession(['linkedin_oauth_state' => 'state-xyz'])
        ->get(route('linkedin.callback', ['code' => 'code', 'state' => 'state-xyz']))
        ->assertRedirect(route('login'));

    Http::assertNothingSent();

    expect(LinkedInAccount::current())->toBeNull();
});

it('lets a signed-in user start the connect flow', function () {
    $response = $this->actingAs(new User)->get(route('linkedin.connect'));

    expect($response->headers->get('Location'))->toContain('https://www.linkedin.com/oauth/v2/authorization');
});
