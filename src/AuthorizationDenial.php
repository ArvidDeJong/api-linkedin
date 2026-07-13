<?php

namespace Darvis\ApiLinkedin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * LinkedIn refused the authorization and sent the reason back on the callback.
 *
 * Two things make that reason awkward to handle, and both are dealt with here so
 * every application does not have to:
 *
 * 1. The description arrives HTML-escaped (`Scope &quot;w_organization_social&quot;
 *    is not authorized`). Rendered as-is in a Blade template it gets escaped a
 *    second time and the user reads the entities.
 * 2. The interesting case — a scope the LinkedIn app does not hold — is only
 *    recognizable from the error code and the English text. That belongs in one
 *    place, not in every host application.
 *
 * @see Scopes
 */
final class AuthorizationDenial
{
    private const SCOPE_ERROR = 'unauthorized_scope_error';

    private function __construct(
        /** LinkedIn's error code, e.g. `unauthorized_scope_error` or `user_cancelled_login`. */
        public readonly string $error,
        /** The human-readable reason, already HTML-decoded. */
        public readonly string $description,
    ) {}

    /**
     * The denial carried by this callback request, or null when LinkedIn did not
     * refuse (the happy path, where a `code` is present instead).
     */
    public static function fromCallback(Request $request): ?self
    {
        if (! $request->filled('error')) {
            return null;
        }

        $error = (string) $request->string('error');

        return new self(
            error: $error,
            description: html_entity_decode(
                (string) $request->string('error_description', $error),
                ENT_QUOTES,
            ),
        );
    }

    /**
     * The LinkedIn app was never authorized for one of the requested scopes.
     *
     * Retrying is pointless: this is not a member declining consent but a product
     * missing on the app itself (nearly always the Community Management API). The
     * way out is to request that product, or to connect with {@see Scopes::MEMBER}
     * only.
     */
    public function isScopeProblem(): bool
    {
        return $this->error === self::SCOPE_ERROR
            || Str::contains($this->description, 'scope', ignoreCase: true);
    }

    /**
     * The scope LinkedIn named, when it named one.
     */
    public function missingScope(): ?string
    {
        preg_match('/scope\s+"([^"]+)"/i', $this->description, $matches);

        return $matches[1] ?? null;
    }

    /**
     * A denial that only concerns company pages can be recovered from by
     * connecting with the member scopes alone.
     */
    public function isRecoverableWithMemberScopes(): bool
    {
        if (! $this->isScopeProblem()) {
            return false;
        }

        $scope = $this->missingScope();

        return $scope === null || Scopes::requiresCommunityManagementApi($scope);
    }

    public function __toString(): string
    {
        return $this->description;
    }
}
