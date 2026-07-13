<?php

namespace Darvis\ApiLinkedin\Services;

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Illuminate\Support\Facades\Http;

/**
 * Publishes posts through the LinkedIn Posts API (`/rest/posts`) on behalf of a
 * member or a company page. A URL in the commentary lets LinkedIn build the link
 * preview itself from the Open Graph tags of that page.
 */
class LinkedInPublisher
{
    private const POSTS_URL = 'https://api.linkedin.com/rest/posts';

    public function __construct(private LinkedInOAuth $oauth) {}

    /**
     * Publish a post on behalf of $authorUrn with the given commentary.
     *
     * @return array{urn: string, permalink: string}
     *
     * @throws LinkedInException on an expired connection or an API error.
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
            throw LinkedInApiException::from(
                LinkedInApiException::OPERATION_PUBLISH,
                $response,
                'LinkedIn rejected the post',
            );
        }

        $urn = $response->header('x-restli-id') ?: (string) $response->json('id', '');

        return [
            'urn' => $urn,
            'permalink' => $urn !== '' ? 'https://www.linkedin.com/feed/update/'.$urn.'/' : '',
        ];
    }

    /**
     * Escapes the characters LinkedIn reserves in the commentary field. The
     * backslash goes first, so existing backslashes are not escaped twice.
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
