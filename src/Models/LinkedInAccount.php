<?php

namespace Darvis\ApiLinkedin\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * De gekoppelde LinkedIn-verbinding (één actieve verbinding). Bevat het
 * OAuth-token van het geautoriseerde lid; hetzelfde token kan namens het lid
 * én — met de juiste scope + rechten — namens de bedrijfspagina posten.
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
     * De huidige (meest recente) LinkedIn-verbinding, of null als er niet
     * gekoppeld is.
     */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * Het access-token is verlopen (of verloopt binnen een minuut).
     */
    public function tokenHasExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->subMinute()->isPast();
    }

    /**
     * Er is een geldig refresh-token beschikbaar om het access-token te
     * vernieuwen zonder opnieuw te koppelen.
     */
    public function canRefresh(): bool
    {
        return filled($this->refresh_token)
            && ($this->refresh_token_expires_at === null || $this->refresh_token_expires_at->isFuture());
    }
}
