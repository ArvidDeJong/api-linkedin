<?php

namespace Darvis\ApiLinkedin\Http\Controllers;

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ingebouwde OAuth-afhandeling (verbinden + callback). De routes worden alleen
 * geregistreerd wanneer `linkedin.routes.enabled` aan staat.
 */
class LinkedInController
{
    public function __construct(private LinkedInOAuth $oauth) {}

    /**
     * Start de OAuth-flow: bewaar een CSRF-state en stuur door naar LinkedIn.
     */
    public function connect(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return $this->back($request, error: 'Stel eerst LINKEDIN_CLIENT_ID en LINKEDIN_CLIENT_SECRET in.');
        }

        $state = Str::random(40);
        $request->session()->put($this->key('state_key', 'linkedin_oauth_state'), $state);

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    /**
     * Verwerk de terugkoppeling van LinkedIn: valideer de state, wissel de code
     * in en sla de verbinding op.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull($this->key('state_key', 'linkedin_oauth_state'));

        if ($request->filled('error')) {
            return $this->back($request, error: 'LinkedIn-koppeling geweigerd: '.$request->string('error_description', $request->string('error')));
        }

        if (! $request->filled('code') || $expectedState === null || ! hash_equals($expectedState, (string) $request->string('state'))) {
            return $this->back($request, error: 'Ongeldige of verlopen koppelingssessie. Probeer het opnieuw.');
        }

        try {
            $account = $this->oauth->connectFromCode((string) $request->string('code'));
        } catch (LinkedInException|Throwable $e) {
            report($e);

            return $this->back($request, error: 'Koppelen mislukt: '.$e->getMessage());
        }

        return $this->back($request, status: 'LinkedIn gekoppeld als '.$account->name.'.');
    }

    private function back(Request $request, ?string $status = null, ?string $error = null): RedirectResponse
    {
        $to = config('linkedin.routes.redirect_to');
        $redirect = $to !== null ? redirect()->route($to) : redirect()->to('/');

        if ($status !== null) {
            $redirect->with($this->key('status_key', 'linkedin_status'), $status);
        }

        if ($error !== null) {
            $redirect->with($this->key('error_key', 'linkedin_error'), $error);
        }

        return $redirect;
    }

    private function key(string $name, string $default): string
    {
        return (string) config("linkedin.session.$name", $default);
    }
}
