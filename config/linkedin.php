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
