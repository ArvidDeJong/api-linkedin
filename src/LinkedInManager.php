<?php

namespace Darvis\ApiLinkedin;

use Darvis\ApiLinkedin\Exceptions\LinkedInConfigurationException;
use Darvis\ApiLinkedin\Exceptions\LinkedInNotConnected;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInImages;
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
        public readonly LinkedInImages $images,
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

    /**
     * @param  list<string>|null  $scopes  Overrides the configured scopes; pass
     *                                     {@see Scopes::MEMBER} to connect without
     *                                     the Community Management API.
     */
    public function authorizationUrl(string $state, ?array $scopes = null): string
    {
        return $this->oauth->authorizationUrl($state, $scopes);
    }

    /**
     * @param  list<string>|null  $requestedScopes  The set sent to {@see authorizationUrl()}.
     */
    public function connectFromCode(string $code, ?array $requestedScopes = null): LinkedInAccount
    {
        return $this->oauth->connectFromCode($code, $requestedScopes);
    }

    /**
     * May the current connection list company pages? Both the config and the
     * granted scopes have to allow it — gate your UI on this instead of on the
     * config alone, or you will offer targets that publish into a 403.
     */
    public function canListOrganizations(): bool
    {
        return $this->organizationListingEnabled()
            && (bool) $this->account()?->canListOrganizations();
    }

    /**
     * May the current connection publish on behalf of a company page?
     */
    public function canPostAsOrganization(): bool
    {
        return (bool) $this->account()?->canPostAsOrganization();
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
    public function postAsMember(string $commentary, ?Article $article = null): array
    {
        $account = $this->requireAccount();

        return $this->publisher->publish($account, $account->member_urn, $commentary, $article);
    }

    /**
     * Publish a post on behalf of a company page. Without an explicit URN the
     * default from `linkedin.organization_urn` is used.
     *
     * @return array{urn: string, permalink: string}
     */
    public function postAsOrganization(string $commentary, ?string $organizationUrn = null, ?Article $article = null): array
    {
        $organizationUrn ??= (string) config('linkedin.organization_urn');

        if ($organizationUrn === '') {
            throw new LinkedInConfigurationException('No LinkedIn company page given, and none configured (linkedin.organization_urn).');
        }

        return $this->publisher->publish($this->requireAccount(), $organizationUrn, $commentary, $article);
    }

    /**
     * Upload an image and return its `urn:li:image:...`, to hang on an
     * {@see Article} as the thumbnail.
     *
     * $ownerUrn must be the author the post will be published as — LinkedIn
     * refuses an image owned by anyone else. Pass the raw bytes; where they come
     * from (disk, S3, a generated file) is none of the package's business.
     */
    public function uploadImage(
        string $ownerUrn,
        string $contents,
        string $contentType = 'application/octet-stream',
    ): string {
        return $this->images->upload($this->requireAccount(), $ownerUrn, $contents, $contentType);
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
    public function publish(string $authorUrn, string $commentary, ?Article $article = null): array
    {
        return $this->publisher->publish($this->requireAccount(), $authorUrn, $commentary, $article);
    }

    private function requireAccount(): LinkedInAccount
    {
        return $this->account() ?? throw new LinkedInNotConnected;
    }
}
