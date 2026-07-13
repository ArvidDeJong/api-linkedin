<?php

namespace Darvis\ApiLinkedin\Facades;

use Darvis\ApiLinkedin\LinkedInManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isConfigured()
 * @method static bool organizationEnabled()
 * @method static bool organizationListingEnabled()
 * @method static bool canListOrganizations()
 * @method static bool canPostAsOrganization()
 * @method static string authorizationUrl(string $state, ?array $scopes = null)
 * @method static \Darvis\ApiLinkedin\Models\LinkedInAccount connectFromCode(string $code, ?array $requestedScopes = null)
 * @method static \Darvis\ApiLinkedin\Models\LinkedInAccount|null account()
 * @method static bool isConnected()
 * @method static void disconnect()
 * @method static array{urn: string, permalink: string} postAsMember(string $commentary, ?\Darvis\ApiLinkedin\Article $article = null)
 * @method static array{urn: string, permalink: string} postAsOrganization(string $commentary, ?string $organizationUrn = null, ?\Darvis\ApiLinkedin\Article $article = null)
 * @method static array{urn: string, permalink: string} publish(string $authorUrn, string $commentary, ?\Darvis\ApiLinkedin\Article $article = null)
 * @method static string uploadImage(string $ownerUrn, string $contents, string $contentType = 'application/octet-stream')
 * @method static list<array{urn: string, id: string, name: string, vanity_name: string|null}> organizations(bool $fresh = false)
 * @method static void forgetOrganizations()
 *
 * @see LinkedInManager
 */
class LinkedIn extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LinkedInManager::class;
    }
}
