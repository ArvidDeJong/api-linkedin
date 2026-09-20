<?php

namespace Darvis\ApiLinkedin\Services;

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInConnectionExpired;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Scopes;
use Darvis\ApiLinkedin\Support\LinkedInConfig;
use Illuminate\Support\Facades\Http;

/**
 * Handles the OAuth 2.0 (authorization code) flow with LinkedIn: building the
 * authorization URL, exchanging the code for an access token, fetching the
 * member profile and refreshing expired tokens. Deliberately without an
 * external dependency, using the built-in HTTP client.
 */
class LinkedInOAuth
{
    private const AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    private const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';

    /**
     * Are the client credentials configured?
     */
    public function isConfigured(): bool
    {
        return LinkedInConfig::clientId() !== null
            && LinkedInConfig::clientSecret() !== null;
    }

    /**
     * Is a default company page configured to post on behalf of?
     */
    public function organizationEnabled(): bool
    {
        return LinkedInConfig::organizationUrn() !== null;
    }

    /**
     * May we list the company pages the member administers?
     */
    public function organizationListingEnabled(): bool
    {
        return LinkedInConfig::organizationsEnabled();
    }

    /**
     * The scopes to request by default. Organization scopes are only added when a
     * company page is configured or page listing is enabled — both require the
     * Community Management API, so asking for them unconditionally would lock out
     * apps that only hold "Share on LinkedIn".
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        $scopes = Scopes::MEMBER;

        if ($this->organizationEnabled() || $this->organizationListingEnabled()) {
            $scopes[] = Scopes::POST_AS_ORGANIZATION;
        }

        if ($this->organizationListingEnabled()) {
            $scopes[] = Scopes::LIST_ORGANIZATIONS;
        }

        foreach (LinkedInConfig::extraScopes() as $scope) {
            if (! in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    public function redirectUri(): string
    {
        return route(LinkedInConfig::callbackRouteName());
    }

    /**
     * Build the URL to send the member to.
     *
     * Pass $scopes to request something other than the configured default —
     * {@see Scopes::MEMBER} for a connection that works on any LinkedIn app, even
     * one without the Community Management API. Without this argument the only way
     * to narrow the request would be to mutate the config mid-request.
     *
     * @param  list<string>|null  $scopes
     */
    public function authorizationUrl(string $state, ?array $scopes = null): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => LinkedInConfig::clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', $scopes ?? $this->scopes()),
        ]);
    }

    /**
     * Exchange the authorization code for an access token and store the account.
     *
     * `$requestedScopes` must be the set that was actually sent to
     * {@see authorizationUrl()}; it is only used when LinkedIn omits `scope` from
     * the token response. Leaving it out falls back to the configured scopes,
     * which is a guess: the config may have changed since the redirect, or the
     * flow may have deliberately asked for less. A wrong guess is worse than none,
     * because the stored scopes are what every capability check leans on.
     *
     * @param  list<string>|null  $requestedScopes
     */
    public function connectFromCode(string $code, ?array $requestedScopes = null): LinkedInAccount
    {
        $token = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        $profile = $this->fetchMemberProfile($token['access_token']);

        return LinkedInAccount::query()->updateOrCreate(
            ['member_id' => $profile['sub']],
            [
                'member_urn' => 'urn:li:person:'.$profile['sub'],
                'name' => $profile['name'] ?? 'LinkedIn member',
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'scopes' => $token['scope'] ?? implode(' ', $requestedScopes ?? $this->scopes()),
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : null,
                'refresh_token_expires_at' => isset($token['refresh_token_expires_in'])
                    ? now()->addSeconds((int) $token['refresh_token_expires_in'])
                    : null,
            ],
        );
    }

    /**
     * Return a valid access token, refreshing it when needed and possible.
     *
     * @throws LinkedInException when the token has expired and cannot be
     *                           refreshed (the account must be reconnected).
     */
    public function freshAccessToken(LinkedInAccount $account): string
    {
        if (! $account->tokenHasExpired()) {
            return $account->access_token;
        }

        if (! $account->canRefresh()) {
            throw new LinkedInConnectionExpired;
        }

        // canRefresh() guarantees a refresh token; the cast tells the analyser so.
        $token = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $account->refresh_token,
        ]);

        $account->update([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($token['expires_in'])
                ? now()->addSeconds((int) $token['expires_in'])
                : null,
            'refresh_token_expires_at' => isset($token['refresh_token_expires_in'])
                ? now()->addSeconds((int) $token['refresh_token_expires_in'])
                : $account->refresh_token_expires_at,
        ]);

        return $account->access_token;
    }

    /**
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    private function requestToken(array $params): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, array_merge($params, [
            'client_id' => LinkedInConfig::clientId(),
            'client_secret' => LinkedInConfig::clientSecret(),
        ]));

        if ($response->failed()) {
            throw LinkedInApiException::from(
                LinkedInApiException::OPERATION_TOKEN,
                $response,
                'LinkedIn returned an error while fetching the token',
            );
        }

        return $response->json();
    }

    /**
     * Fetches the profile of the authorized member through OpenID Connect.
     *
     * @return array<string, mixed>
     */
    private function fetchMemberProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::USERINFO_URL);

        if ($response->failed()) {
            throw LinkedInApiException::from(
                LinkedInApiException::OPERATION_PROFILE,
                $response,
                'Could not fetch the LinkedIn profile',
            );
        }

        return $response->json();
    }
}
