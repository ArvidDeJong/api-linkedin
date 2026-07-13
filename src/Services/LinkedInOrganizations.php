<?php

namespace Darvis\ApiLinkedin\Services;

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Exceptions\LinkedInScopeMissing;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Scopes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lists the company pages the connected member administers, through the
 * organizationAcls endpoint. Requires the `r_organization_admin` scope, which is
 * only requested when `linkedin.organizations.enabled` is on — a token issued
 * before that was enabled cannot list pages, so the account must be reconnected.
 */
class LinkedInOrganizations
{
    private const ACLS_URL = 'https://api.linkedin.com/rest/organizationAcls';

    public function __construct(private LinkedInOAuth $oauth) {}

    /**
     * The company pages the member administers. Cached for
     * `linkedin.organizations.cache_ttl` seconds; pass $fresh to bypass it.
     *
     * @return list<array{urn: string, id: string, name: string, vanity_name: string|null}>
     *
     * @throws LinkedInScopeMissing when the token provably lacks `r_organization_admin`.
     * @throws LinkedInException when the connection expired or LinkedIn refuses.
     */
    public function all(LinkedInAccount $account, bool $fresh = false): array
    {
        // Enabling the config does not upgrade an existing token: one minted before
        // the flag was flipped — or by an app without the Community Management API
        // — simply has no `r_organization_admin`. Say so, instead of letting the
        // call come back as an unexplained 403.
        if ($account->lacksScope(Scopes::LIST_ORGANIZATIONS)) {
            throw new LinkedInScopeMissing(Scopes::LIST_ORGANIZATIONS);
        }

        $ttl = (int) config('linkedin.organizations.cache_ttl', 3600);

        if ($ttl <= 0) {
            return $this->fetch($account);
        }

        if ($fresh) {
            $this->forget($account);
        }

        return Cache::remember(
            $this->cacheKey($account),
            $ttl,
            fn (): array => $this->fetch($account),
        );
    }

    /**
     * Drop the cached page list for this account.
     */
    public function forget(LinkedInAccount $account): void
    {
        Cache::forget($this->cacheKey($account));
    }

    /**
     * @return list<array{urn: string, id: string, name: string, vanity_name: string|null}>
     */
    private function fetch(LinkedInAccount $account): array
    {
        $response = Http::withToken($this->oauth->freshAccessToken($account))
            ->withHeaders([
                'LinkedIn-Version' => (string) config('linkedin.api_version'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->get(self::ACLS_URL, [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'state' => 'APPROVED',
                // Decorate each ACL with the organization itself, so we get the
                // name in the same call instead of one request per page.
                'projection' => '(elements*(*,organization~(id,localizedName,vanityName)))',
            ]);

        if ($response->failed()) {
            throw LinkedInApiException::from(
                LinkedInApiException::OPERATION_ORGANIZATIONS,
                $response,
                'Could not fetch the LinkedIn company pages',
            );
        }

        $organizations = [];

        foreach ((array) $response->json('elements', []) as $element) {
            $urn = $element['organization'] ?? null;

            if (! is_string($urn) || $urn === '') {
                continue;
            }

            // The decorated object is keyed with a trailing tilde by Rest.li. It
            // may be absent when the projection is ignored; fall back to the URN.
            $details = (array) ($element['organization~'] ?? []);

            $organizations[] = [
                'urn' => $urn,
                'id' => (string) ($details['id'] ?? Str::afterLast($urn, ':')),
                'name' => (string) ($details['localizedName'] ?? $urn),
                'vanity_name' => isset($details['vanityName']) ? (string) $details['vanityName'] : null,
            ];
        }

        return $organizations;
    }

    private function cacheKey(LinkedInAccount $account): string
    {
        return 'linkedin.organizations.'.$account->getKey();
    }
}
