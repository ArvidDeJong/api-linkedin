<?php

namespace Darvis\ApiLinkedin;

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;

/**
 * Ergonomische ingang tot de koppeling; achterliggend gebruikt hij de OAuth- en
 * publisher-services. Bereikbaar via de {@see LinkedIn} facade.
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
     * De huidige gekoppelde verbinding, of null.
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
     * Plaats een bericht namens het gekoppelde lid.
     *
     * @return array{urn: string, permalink: string}
     */
    public function postAsMember(string $commentary): array
    {
        $account = $this->requireAccount();

        return $this->publisher->publish($account, $account->member_urn, $commentary);
    }

    /**
     * Plaats een bericht namens de bedrijfspagina.
     *
     * @return array{urn: string, permalink: string}
     */
    public function postAsOrganization(string $commentary): array
    {
        $organizationUrn = (string) config('linkedin.organization_urn');

        if ($organizationUrn === '') {
            throw new LinkedInException('Geen LinkedIn-bedrijfspagina ingesteld (linkedin.organization_urn).');
        }

        return $this->publisher->publish($this->requireAccount(), $organizationUrn, $commentary);
    }

    /**
     * Plaats een bericht namens een willekeurige auteur-URN.
     *
     * @return array{urn: string, permalink: string}
     */
    public function publish(string $authorUrn, string $commentary): array
    {
        return $this->publisher->publish($this->requireAccount(), $authorUrn, $commentary);
    }

    private function requireAccount(): LinkedInAccount
    {
        return $this->account() ?? throw new LinkedInException('Geen actieve LinkedIn-koppeling.');
    }
}
