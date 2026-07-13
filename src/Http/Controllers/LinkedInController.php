<?php

namespace Darvis\ApiLinkedin\Http\Controllers;

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Built-in OAuth handling (connect + callback). These routes are only registered
 * when `linkedin.routes.enabled` is on.
 */
class LinkedInController
{
    public function __construct(private LinkedInOAuth $oauth) {}

    /**
     * Start the OAuth flow: store a CSRF state and redirect to LinkedIn.
     */
    public function connect(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return $this->back($request, error: 'Configure LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET first.');
        }

        $state = Str::random(40);
        $request->session()->put($this->key('state_key', 'linkedin_oauth_state'), $state);

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    /**
     * Handle the callback from LinkedIn: validate the state, exchange the code
     * and store the connection.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull($this->key('state_key', 'linkedin_oauth_state'));

        if ($request->filled('error')) {
            return $this->back($request, error: 'LinkedIn connection denied: '.$request->string('error_description', $request->string('error')));
        }

        if (! $request->filled('code') || $expectedState === null || ! hash_equals($expectedState, (string) $request->string('state'))) {
            return $this->back($request, error: 'Invalid or expired connection session. Please try again.');
        }

        try {
            $account = $this->oauth->connectFromCode((string) $request->string('code'));
        } catch (LinkedInException|Throwable $e) {
            report($e);

            return $this->back($request, error: 'Connecting failed: '.$e->getMessage());
        }

        return $this->back($request, status: 'LinkedIn connected as '.$account->name.'.');
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
