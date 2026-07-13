<?php

namespace Darvis\ApiLinkedin;

use Darvis\ApiLinkedin\Exceptions\LinkedInConfigurationException;
use Darvis\ApiLinkedin\Exceptions\LinkedInNotConnected;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Darvis\ApiLinkedin\Services\LinkedInOrganizations;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;

/**
 * Ergonomic entry point to the integration; under the hood it uses the OAuth and
 * publisher services. Reachable through the {@see LinkedIn} facade.
 */
class LinkedInManager
{
    public function __construct(
        public readonly LinkedInOAuth $oauth,
        public readonly LinkedInPublisher $publisher,
        public readonly LinkedInOrganizations $organizations,
    ) {}

    public function isConfigured(): bool
    {
        return $this->oauth->isConfigured();
    }

    public function organizationEnabled(): bool
    {
        return $this->oauth->organizationEnabled();
    }

    public function organizationListingEnabled(): bool
    {
        return $this->oauth->organizationListingEnabled();
    }

    public function authorizationUrl(string $state): string
    {
        return $this->oauth->authorizationUrl($state);
    }

    public function connectFromCode(string $code): LinkedInAccount
    {
        return $this->oauth->connectFromCode($code);
    }

    /**
     * The currently connected account, or null.
     */
    public function account(): ?LinkedInAccount
    {
        return LinkedInAccount::current();
    }

    public function isConnected(): bool
    {
        return $this->account() !== null;
    }

    public function disconnect(): void
    {
        LinkedInAccount::query()->delete();
    }

    /**
     * Publish a post on behalf of the connected member.
     *
     * @return array{urn: string, permalink: string}
     */
    public function postAsMember(string $commentary): array
    {
        $account = $this->requireAccount();

        return $this->publisher->publish($account, $account->member_urn, $commentary);
    }

    /**
     * Publish a post on behalf of a company page. Without an explicit URN the
     * default from `linkedin.organization_urn` is used.
     *
     * @return array{urn: string, permalink: string}
     */
    public function postAsOrganization(string $commentary, ?string $organizationUrn = null): array
    {
        $organizationUrn ??= (string) config('linkedin.organization_urn');

        if ($organizationUrn === '') {
            throw new LinkedInConfigurationException('No LinkedIn company page given, and none configured (linkedin.organization_urn).');
        }

        return $this->publisher->publish($this->requireAccount(), $organizationUrn, $commentary);
    }

    /**
     * The company pages the connected member administers. Requires
     * `linkedin.organizations.enabled` and a token carrying `r_organization_admin`.
     *
     * @return list<array{urn: string, id: string, name: string, vanity_name: string|null}>
     */
    public function organizations(bool $fresh = false): array
    {
        return $this->organizations->all($this->requireAccount(), $fresh);
    }

    /**
     * Drop the cached company page list.
     */
    public function forgetOrganizations(): void
    {
        if ($account = $this->account()) {
            $this->organizations->forget($account);
        }
    }

    /**
     * Publish a post on behalf of an arbitrary author URN.
     *
     * @return array{urn: string, permalink: string}
     */
    public function publish(string $authorUrn, string $commentary): array
    {
        return $this->publisher->publish($this->requireAccount(), $authorUrn, $commentary);
    }

    private function requireAccount(): LinkedInAccount
    {
        return $this->account() ?? throw new LinkedInNotConnected;
    }
}
