<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LinkedIn app credentials
    |--------------------------------------------------------------------------
    |
    | Client ID and secret of your app at https://www.linkedin.com/developers/apps.
    | Request the products "Share on LinkedIn" (personal profile) and/or
    | "Community Management API" (company page).
    |
    */

    'client_id' => env('LINKEDIN_CLIENT_ID'),

    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Company page
    |--------------------------------------------------------------------------
    |
    | URN of the company page to post on behalf of, e.g.
    | "urn:li:organization:1234567". Leave empty to only post on the personal
    | profile (the w_organization_social scope is then not requested either).
    |
    */

    'organization_urn' => env('LINKEDIN_ORGANIZATION_URN'),

    /*
    |--------------------------------------------------------------------------
    | API version
    |--------------------------------------------------------------------------
    |
    | LinkedIn uses monthly API versions (format YYYYMM), each valid for about a
    | year. Set this to a valid, recent version.
    |
    */

    'api_version' => env('LINKEDIN_API_VERSION', '202601'),

    /*
    |--------------------------------------------------------------------------
    | Extra scopes
    |--------------------------------------------------------------------------
    |
    | By default openid, profile and w_member_social are requested (plus
    | w_organization_social when an organization_urn is configured). Add any
    | additional scopes here.
    |
    */

    'scopes' => [],

    /*
    |--------------------------------------------------------------------------
    | Listing company pages
    |--------------------------------------------------------------------------
    |
    | Enable this to let LinkedIn::organizations() list the company pages the
    | connected member administers, so a user can pick a target instead of
    | hardcoding one URN. It adds the `r_organization_admin` scope (and
    | `w_organization_social`) to the authorization request, which requires
    | Community Management API access.
    |
    | Note: after turning this on you must reconnect. Existing tokens were issued
    | without the scope and cannot list pages.
    |
    */

    'organizations' => [
        'enabled' => env('LINKEDIN_ORGANIZATIONS_ENABLED', false),
        // Seconds to cache the list; company pages rarely change. 0 = no cache.
        'cache_ttl' => env('LINKEDIN_ORGANIZATIONS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database table
    |--------------------------------------------------------------------------
    */

    'table' => 'linkedin_accounts',

    /*
    |--------------------------------------------------------------------------
    | OAuth routes
    |--------------------------------------------------------------------------
    |
    | The built-in connect/callback routes. Set `enabled` to false to wire the
    | OAuth flow yourself; use `callback_name` to point the service at your own
    | callback route (for the redirect_uri). `redirect_to` is the route the user
    | is sent back to after the flow succeeds or fails.
    |
    */

    'routes' => [
        'enabled' => env('LINKEDIN_ROUTES_ENABLED', true),
        'prefix' => env('LINKEDIN_ROUTE_PREFIX', 'linkedin'),
        'middleware' => ['web'],
        'connect_name' => 'linkedin.connect',
        'callback_name' => 'linkedin.callback',
        'redirect_to' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session keys
    |--------------------------------------------------------------------------
    |
    | Keys for the CSRF state and the flash messages after the OAuth flow.
    |
    */

    'session' => [
        'state_key' => 'linkedin_oauth_state',
        'status_key' => 'linkedin_status',
        'error_key' => 'linkedin_error',
    ],

];
