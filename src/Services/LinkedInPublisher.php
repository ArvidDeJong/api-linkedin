<?php

namespace Darvis\ApiLinkedin\Services;

use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Illuminate\Support\Facades\Http;

/**
 * Plaatst berichten via de LinkedIn Posts API (`/rest/posts`) namens een lid of
 * een bedrijfspagina. Een URL in de begeleidende tekst laat LinkedIn zelf de
 * linkpreview uit de Open Graph-tags van de pagina opbouwen.
 */
class LinkedInPublisher
{
    private const POSTS_URL = 'https://api.linkedin.com/rest/posts';

    public function __construct(private LinkedInOAuth $oauth) {}

    /**
     * Plaats een bericht namens $authorUrn met de opgegeven begeleidende tekst.
     *
     * @return array{urn: string, permalink: string}
     *
     * @throws LinkedInException bij een verlopen koppeling of een API-fout.
     */
    public function publish(LinkedInAccount $account, string $authorUrn, string $commentary): array
    {
        $token = $this->oauth->freshAccessToken($account);

        $response = Http::withToken($token)
            ->withHeaders([
                'LinkedIn-Version' => (string) config('linkedin.api_version'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->post(self::POSTS_URL, [
                'author' => $authorUrn,
                'commentary' => $this->escapeCommentary($commentary),
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]);

        if ($response->failed()) {
            throw new LinkedInException('LinkedIn weigerde het bericht: '.$response->body());
        }

        $urn = $response->header('x-restli-id') ?: (string) $response->json('id', '');

        return [
            'urn' => $urn,
            'permalink' => $urn !== '' ? 'https://www.linkedin.com/feed/update/'.$urn.'/' : '',
        ];
    }

    /**
     * Escapet de tekens die LinkedIn in het commentary-veld reserveert. De
     * backslash gaat eerst, zodat bestaande backslashes niet dubbel geëscaped
     * raken.
     */
    private function escapeCommentary(string $text): string
    {
        $reserved = ['\\', '|', '{', '}', '@', '[', ']', '(', ')', '<', '>', '#', '*', '_', '~'];

        return str_replace(
            $reserved,
            array_map(static fn (string $char): string => '\\'.$char, $reserved),
            $text,
        );
    }
}
