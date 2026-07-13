<?php

namespace Darvis\ApiLinkedin\Services;

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
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
        return filled(config('linkedin.client_id'))
            && filled(config('linkedin.client_secret'));
    }

    /**
     * Is a company page configured to post on behalf of?
     */
    public function organizationEnabled(): bool
    {
        return filled(config('linkedin.organization_urn'));
    }

    /**
     * The requested scopes. `w_organization_social` is only requested when a
     * company page is configured (requires the Community Management API).
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        $scopes = ['openid', 'profile', 'w_member_social'];

        if ($this->organizationEnabled()) {
            $scopes[] = 'w_organization_social';
        }

        foreach ((array) config('linkedin.scopes', []) as $scope) {
            if (! in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    public function redirectUri(): string
    {
        return route(config('linkedin.routes.callback_name', 'linkedin.callback'));
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('linkedin.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
        ]);
    }

    /**
     * Exchange the authorization code for an access token and store the account.
     */
    public function connectFromCode(string $code): LinkedInAccount
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
                'scopes' => $token['scope'] ?? implode(' ', $this->scopes()),
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
            throw new LinkedInException('The LinkedIn connection has expired. Please reconnect the account.');
        }

        $token = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
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
            'client_id' => config('linkedin.client_id'),
            'client_secret' => config('linkedin.client_secret'),
        ]));

        if ($response->failed()) {
            throw new LinkedInException('LinkedIn returned an error while fetching the token: '.$response->body());
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
            throw new LinkedInException('Could not fetch the LinkedIn profile: '.$response->body());
        }

        return $response->json();
    }
}
