<?php

namespace Darvis\ApiLinkedin\Facades;

use Darvis\ApiLinkedin\LinkedInManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isConfigured()
 * @method static bool organizationEnabled()
 * @method static string authorizationUrl(string $state)
 * @method static \Darvis\ApiLinkedin\Models\LinkedInAccount connectFromCode(string $code)
 * @method static \Darvis\ApiLinkedin\Models\LinkedInAccount|null account()
 * @method static bool isConnected()
 * @method static void disconnect()
 * @method static array{urn: string, permalink: string} postAsMember(string $commentary)
 * @method static array{urn: string, permalink: string} postAsOrganization(string $commentary)
 * @method static array{urn: string, permalink: string} publish(string $authorUrn, string $commentary)
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
