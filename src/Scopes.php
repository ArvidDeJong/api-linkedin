<?php

namespace Darvis\ApiLinkedin;

/**
 * The OAuth scopes this package works with, grouped by the LinkedIn product that
 * grants them.
 *
 * Which product an app holds decides which scopes it may ask for, and LinkedIn
 * refuses the *entire* authorization when a single requested scope is not
 * authorized — the member never even sees the consent screen. Asking only for
 * what you can actually get is therefore not an optimization but a requirement.
 */
final class Scopes
{
    /** Granted by "Sign In with LinkedIn using OpenID Connect". */
    public const OPENID = 'openid';

    public const PROFILE = 'profile';

    /** Granted by "Share on LinkedIn". */
    public const POST_AS_MEMBER = 'w_member_social';

    /** Granted by the "Community Management API". */
    public const POST_AS_ORGANIZATION = 'w_organization_social';

    /** Granted by the "Community Management API". */
    public const LIST_ORGANIZATIONS = 'r_organization_admin';

    /**
     * Everything needed to post on the member's own profile. Every LinkedIn app
     * can hold these, so an authorization limited to this set always succeeds.
     *
     * @var list<string>
     */
    public const MEMBER = [self::OPENID, self::PROFILE, self::POST_AS_MEMBER];

    /**
     * The scopes that require the Community Management API — the product an app
     * is most likely to be missing.
     *
     * @var list<string>
     */
    public const ORGANIZATION = [self::POST_AS_ORGANIZATION, self::LIST_ORGANIZATIONS];

    /**
     * Does this scope require the Community Management API?
     */
    public static function requiresCommunityManagementApi(string $scope): bool
    {
        return in_array($scope, self::ORGANIZATION, true);
    }
}
