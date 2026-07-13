<?php

namespace Darvis\ApiLinkedin;

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
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
    ) {}

    public function isConfigured(): bool
    {
        return $this->oauth->isConfigured();
    }

    public function organizationEnabled(): bool
    {
        return $this->oauth->organizationEnabled();
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
     * Publish a post on behalf of the company page.
     *
     * @return array{urn: string, permalink: string}
     */
    public function postAsOrganization(string $commentary): array
    {
        $organizationUrn = (string) config('linkedin.organization_urn');

        if ($organizationUrn === '') {
            throw new LinkedInException('No LinkedIn company page configured (linkedin.organization_urn).');
        }

        return $this->publisher->publish($this->requireAccount(), $organizationUrn, $commentary);
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
        return $this->account() ?? throw new LinkedInException('No active LinkedIn connection.');
    }
}
