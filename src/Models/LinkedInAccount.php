<?php

namespace Darvis\ApiLinkedin\Models;

use Carbon\CarbonInterface;
use Darvis\ApiLinkedin\Scopes;
use Illuminate\Database\Eloquent\Model;

/**
 * The connected LinkedIn account (a single active connection). Holds the OAuth
 * token of the authorized member; that same token can post on behalf of the
 * member and — with the right scope and permissions — the company page.
 *
 * @property int $id
 * @property string $member_id
 * @property string $member_urn
 * @property string $name
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string|null $scopes
 * @property CarbonInterface|null $token_expires_at
 * @property CarbonInterface|null $refresh_token_expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class LinkedInAccount extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('linkedin.table', 'linkedin_accounts');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }

    /**
     * The current (most recent) LinkedIn connection, or null when no account is
     * connected.
     */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * The scopes LinkedIn granted this connection, or null when they are unknown
     * (an account stored before 1.4, or a token response without a `scope`).
     *
     * This — not the config — is what the connection may actually do. The two
     * drift apart the moment the LinkedIn app lacks a product: the config still
     * asks for company pages while the granted token only covers the profile.
     *
     * @return list<string>|null
     */
    public function grantedScopes(): ?array
    {
        if (blank($this->scopes)) {
            return null;
        }

        return array_values(array_filter(explode(' ', $this->scopes)));
    }

    /**
     * Are the granted scopes known at all? When they are not, neither
     * {@see hasScope()} nor {@see lacksScope()} can tell you anything.
     */
    public function knowsScopes(): bool
    {
        return $this->grantedScopes() !== null;
    }

    /**
     * The scope was certainly granted. False when the scope set is unknown — so
     * gate features you offer on this: only promise what you know you can do.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->grantedScopes() ?? [], true);
    }

    /**
     * The scope was certainly *not* granted. False when the scope set is unknown
     * — so refuse calls on this: only block what you know will fail.
     *
     * Note that `hasScope()` and `lacksScope()` are both false when the scopes
     * are unknown; they are deliberately not each other's negation.
     */
    public function lacksScope(string $scope): bool
    {
        return $this->knowsScopes() && ! $this->hasScope($scope);
    }

    /**
     * This connection may publish on behalf of a company page.
     */
    public function canPostAsOrganization(): bool
    {
        return $this->hasScope(Scopes::POST_AS_ORGANIZATION);
    }

    /**
     * This connection may list the company pages the member administers.
     */
    public function canListOrganizations(): bool
    {
        return $this->hasScope(Scopes::LIST_ORGANIZATIONS);
    }

    /**
     * The access token has expired (or expires within a minute).
     */
    public function tokenHasExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->subMinute()->isPast();
    }

    /**
     * A valid refresh token is available to renew the access token without
     * reconnecting.
     */
    public function canRefresh(): bool
    {
        return filled($this->refresh_token)
            && ($this->refresh_token_expires_at === null || $this->refresh_token_expires_at->isFuture());
    }
}
