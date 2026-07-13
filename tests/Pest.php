<?php

use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

/**
 * A connected account. Override `scopes` to mimic what LinkedIn granted; leave it
 * out to mimic a connection stored before 1.4, whose scopes are unknown.
 *
 * @param  array<string, mixed>  $attributes
 */
function account(array $attributes = []): LinkedInAccount
{
    return LinkedInAccount::create(array_merge([
        'member_id' => '1',
        'member_urn' => 'urn:li:person:1',
        'name' => 'Tester',
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'token_expires_at' => now()->addDays(30),
        'refresh_token_expires_at' => now()->addYear(),
    ], $attributes));
}
