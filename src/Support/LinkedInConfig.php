<?php

declare(strict_types=1);

namespace Darvis\ApiLinkedin\Support;

/**
 * The one place that reads the package config. Callers ask this class, so a default is written
 * once and a caller cannot quietly disagree with the config file about what it is.
 */
final class LinkedInConfig
{
    /**
     * Client ID of the LinkedIn app.
     */
    public static function clientId(): ?string
    {
        return self::string('linkedin.client_id');
    }

    /**
     * Client secret of the LinkedIn app.
     */
    public static function clientSecret(): ?string
    {
        return self::string('linkedin.client_secret');
    }

    /**
     * URN of the company page to post on behalf of, or null for the personal profile only.
     */
    public static function organizationUrn(): ?string
    {
        return self::string('linkedin.organization_urn');
    }

    /**
     * The monthly LinkedIn API version (YYYYMM) sent as the LinkedIn-Version header.
     */
    public static function apiVersion(): string
    {
        return (string) config('linkedin.api_version', '202601');
    }

    /**
     * Extra scopes on top of the ones the package derives itself.
     *
     * @return list<string>
     */
    public static function extraScopes(): array
    {
        return array_values(array_map('strval', (array) config('linkedin.scopes', [])));
    }

    /**
     * Whether LinkedIn::organizations() may list the pages the member administers.
     */
    public static function organizationsEnabled(): bool
    {
        return (bool) config('linkedin.organizations.enabled', false);
    }

    /**
     * Seconds to cache that list of company pages. 0 means no cache.
     */
    public static function organizationsCacheTtl(): int
    {
        return (int) config('linkedin.organizations.cache_ttl', 3600);
    }

    /**
     * Table the connected accounts are stored in.
     */
    public static function table(): string
    {
        return (string) config('linkedin.table', 'linkedin_accounts');
    }

    /**
     * Whether the built-in connect and callback routes are registered.
     */
    public static function routesEnabled(): bool
    {
        return (bool) config('linkedin.routes.enabled', true);
    }

    /**
     * URL prefix of the built-in routes.
     */
    public static function routePrefix(): string
    {
        return (string) config('linkedin.routes.prefix', 'linkedin');
    }

    /**
     * Middleware the built-in routes run through.
     *
     * @return list<string>
     */
    public static function routeMiddleware(): array
    {
        return array_values(array_map('strval', (array) config('linkedin.routes.middleware', ['web'])));
    }

    /**
     * Route name of the connect route.
     */
    public static function connectRouteName(): string
    {
        return (string) config('linkedin.routes.connect_name', 'linkedin.connect');
    }

    /**
     * Route name of the callback route. The redirect_uri is built from it.
     */
    public static function callbackRouteName(): string
    {
        return (string) config('linkedin.routes.callback_name', 'linkedin.callback');
    }

    /**
     * Route the member is sent back to after the flow, or null for the site root.
     */
    public static function redirectTo(): ?string
    {
        return self::string('linkedin.routes.redirect_to');
    }

    /**
     * Session key holding the CSRF state during the flow.
     */
    public static function stateKey(): string
    {
        return (string) config('linkedin.session.state_key', 'linkedin_oauth_state');
    }

    /**
     * Session key holding the scopes that were requested.
     */
    public static function scopesKey(): string
    {
        return (string) config('linkedin.session.scopes_key', 'linkedin_requested_scopes');
    }

    /**
     * Flash key for the status message after the flow.
     */
    public static function statusKey(): string
    {
        return (string) config('linkedin.session.status_key', 'linkedin_status');
    }

    /**
     * Flash key for the error message after the flow.
     */
    public static function errorKey(): string
    {
        return (string) config('linkedin.session.error_key', 'linkedin_error');
    }

    /**
     * A configured string, with "not filled in" and "empty" treated the same.
     */
    private static function string(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
