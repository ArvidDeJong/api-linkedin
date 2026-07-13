<?php

namespace Darvis\ApiLinkedin\Services;

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Illuminate\Support\Facades\Http;

/**
 * Regelt de OAuth 2.0 (authorization code) flow met LinkedIn: de autorisatie-URL
 * opbouwen, de code inwisselen voor een access-token, het lidprofiel ophalen en
 * verlopen tokens vernieuwen. Bewust zonder externe dependency, met de
 * ingebouwde HTTP-client.
 */
class LinkedInOAuth
{
    private const AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    private const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';

    /**
     * Zijn de client-credentials ingesteld?
     */
    public function isConfigured(): bool
    {
        return filled(config('linkedin.client_id'))
            && filled(config('linkedin.client_secret'));
    }

    /**
     * Is er een bedrijfspagina geconfigureerd om namens te posten?
     */
    public function organizationEnabled(): bool
    {
        return filled(config('linkedin.organization_urn'));
    }

    /**
     * De aangevraagde scopes. `w_organization_social` wordt alleen gevraagd als
     * er een bedrijfspagina is ingesteld (vereist Community Management API).
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
     * Wissel de autorisatiecode in voor een access-token en sla de verbinding op.
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
                'name' => $profile['name'] ?? 'LinkedIn-lid',
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
     * Geef een geldig access-token terug; ververs het indien nodig én mogelijk.
     *
     * @throws LinkedInException wanneer het token verlopen is en niet te
     *                           vernieuwen valt (opnieuw koppelen nodig).
     */
    public function freshAccessToken(LinkedInAccount $account): string
    {
        if (! $account->tokenHasExpired()) {
            return $account->access_token;
        }

        if (! $account->canRefresh()) {
            throw new LinkedInException('De LinkedIn-koppeling is verlopen. Koppel het account opnieuw.');
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
            throw new LinkedInException('LinkedIn gaf een fout bij het ophalen van het token: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Haalt het profiel van het geautoriseerde lid op via OpenID Connect.
     *
     * @return array<string, mixed>
     */
    private function fetchMemberProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::USERINFO_URL);

        if ($response->failed()) {
            throw new LinkedInException('Kon het LinkedIn-profiel niet ophalen: '.$response->body());
        }

        return $response->json();
    }
}
