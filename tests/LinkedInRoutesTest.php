<?php

use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Illuminate\Support\Facades\Http;

it('registreert de connect- en callback-routes', function () {
    expect(route('linkedin.connect'))->toContain('/linkedin/connect')
        ->and(route('linkedin.callback'))->toContain('/linkedin/callback');
});

it('stuurt de connect-route door naar de LinkedIn-autorisatie', function () {
    $response = $this->get(route('linkedin.connect'));

    $response->assertRedirect();

    expect($response->headers->get('Location'))->toContain('https://www.linkedin.com/oauth/v2/authorization');
    expect(session('linkedin_oauth_state'))->not->toBeNull();
});

it('koppelt via de callback en slaat de verbinding op', function () {
    Http::fake([
        'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'token',
            'expires_in' => 5184000,
        ]),
        'https://api.linkedin.com/v2/userinfo' => Http::response([
            'sub' => '55555',
            'name' => 'Callback Lid',
        ]),
    ]);

    $this->withSession(['linkedin_oauth_state' => 'state-xyz'])
        ->get(route('linkedin.callback', ['code' => 'code', 'state' => 'state-xyz']))
        ->assertSessionHas('linkedin_status');

    expect(LinkedInAccount::current()?->member_urn)->toBe('urn:li:person:55555');
});

it('weigert de callback bij een verkeerde state', function () {
    $this->withSession(['linkedin_oauth_state' => 'verwacht'])
        ->get(route('linkedin.callback', ['code' => 'code', 'state' => 'fout']))
        ->assertSessionHas('linkedin_error');

    expect(LinkedInAccount::current())->toBeNull();
});
