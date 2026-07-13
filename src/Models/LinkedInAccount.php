<?php

namespace Darvis\ApiLinkedin\Models;

use Carbon\CarbonInterface;
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
