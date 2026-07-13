<?php

use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Illuminate\Support\Facades\Http;

it('registers the connect and callback routes', function () {
    expect(route('linkedin.connect'))->toContain('/linkedin/connect')
        ->and(route('linkedin.callback'))->toContain('/linkedin/callback');
});

it('redirects the connect route to the LinkedIn authorization page', function () {
    $response = $this->get(route('linkedin.connect'));

    $response->assertRedirect();

    expect($response->headers->get('Location'))->toContain('https://www.linkedin.com/oauth/v2/authorization');
    expect(session('linkedin_oauth_state'))->not->toBeNull();
});

it('connects through the callback and stores the connection', function () {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'token',
            'expires_in' => 5184000,
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response([
            'sub' => '55555',
            'name' => 'Callback Member',
        ]),
    ]);

    $this->withSession(['linkedin_oauth_state' => 'state-xyz'])
        ->get(route('linkedin.callback', ['code' => 'code', 'state' => 'state-xyz']))
        ->assertSessionHas('linkedin_status');

    expect(LinkedInAccount::current()?->member_urn)->toBe('urn:li:person:55555');
});

it('rejects the callback on a state mismatch', function () {
    $this->withSession(['linkedin_oauth_state' => 'expected'])
        ->get(route('linkedin.callback', ['code' => 'code', 'state' => 'wrong']))
        ->assertSessionHas('linkedin_error');

    expect(LinkedInAccount::current())->toBeNull();
});
