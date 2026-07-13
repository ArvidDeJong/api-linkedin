<?php

namespace Darvis\ApiLinkedin\Http\Controllers;

use Darvis\ApiLinkedin\AuthorizationDenial;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Scopes;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Built-in OAuth handling (connect + callback). These routes are only registered
 * when `linkedin.routes.enabled` is on.
 *
 * The flow has two shapes. By default it asks for whatever the config implies;
 * with `?profile_only=1` it asks for {@see Scopes::MEMBER} only. That second one
 * exists because LinkedIn rejects the *entire* authorization over one scope the
 * app does not hold — without the Community Management API the member never even
 * reaches the consent screen, and there is no way back other than asking for less.
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

        $scopes = $request->boolean('profile_only') ? Scopes::MEMBER : $this->oauth->scopes();
        $state = Str::random(40);

        $request->session()->put($this->key('state_key', 'linkedin_oauth_state'), $state);
        // Remember what we asked for: the callback needs it to record the granted
        // scopes honestly when LinkedIn leaves `scope` out of the token response.
        $request->session()->put($this->key('scopes_key', 'linkedin_requested_scopes'), $scopes);

        return redirect()->away($this->oauth->authorizationUrl($state, $scopes));
    }

    /**
     * Handle the callback from LinkedIn: validate the state, exchange the code
     * and store the connection.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull($this->key('state_key', 'linkedin_oauth_state'));
        $requestedScopes = $request->session()->pull($this->key('scopes_key', 'linkedin_requested_scopes'));

        if ($denial = AuthorizationDenial::fromCallback($request)) {
            return $this->back($request, error: $this->explain($denial));
        }

        if (! $request->filled('code') || $expectedState === null || ! hash_equals($expectedState, (string) $request->string('state'))) {
            return $this->back($request, error: 'Invalid or expired connection session. Please try again.');
        }

        try {
            $account = $this->oauth->connectFromCode(
                (string) $request->string('code'),
                is_array($requestedScopes) ? array_values($requestedScopes) : null,
            );
        } catch (LinkedInException|Throwable $e) {
            report($e);

            return $this->back($request, error: 'Connecting failed: '.$e->getMessage());
        }

        return $this->back($request, status: 'LinkedIn connected as '.$account->name.'.');
    }

    /**
     * A denial over a scope is not a member saying no — it is a product missing on
     * the LinkedIn app, so retrying the same thing will fail again. Name the way
     * out instead of echoing LinkedIn's wording.
     */
    private function explain(AuthorizationDenial $denial): string
    {
        if ($denial->isRecoverableWithMemberScopes()) {
            return 'LinkedIn connection denied: '.$denial->description
                .'. Your LinkedIn app is missing the "Community Management API" product, which company pages require. '
                .'Request it, or connect with your personal profile only.';
        }

        return 'LinkedIn connection denied: '.$denial->description;
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
