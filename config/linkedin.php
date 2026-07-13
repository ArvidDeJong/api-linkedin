<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LinkedIn app-credentials
    |--------------------------------------------------------------------------
    |
    | Client ID en secret van je app op https://www.linkedin.com/developers/apps.
    | Vraag de producten "Share on LinkedIn" (persoonlijk profiel) en/of
    | "Community Management API" (bedrijfspagina) aan.
    |
    */

    'client_id' => env('LINKEDIN_CLIENT_ID'),

    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Bedrijfspagina
    |--------------------------------------------------------------------------
    |
    | URN van de bedrijfspagina om namens te posten, bijv.
    | "urn:li:organization:1234567". Laat leeg om alleen op het persoonlijke
    | profiel te kunnen posten (dan wordt de scope w_organization_social ook
    | niet aangevraagd).
    |
    */

    'organization_urn' => env('LINKEDIN_ORGANIZATION_URN'),

    /*
    |--------------------------------------------------------------------------
    | API-versie
    |--------------------------------------------------------------------------
    |
    | LinkedIn gebruikt maandelijkse API-versies (formaat JJJJMM), elk ~1 jaar
    | geldig. Zet dit op een geldige, recente versie.
    |
    */

    'api_version' => env('LINKEDIN_API_VERSION', '202601'),

    /*
    |--------------------------------------------------------------------------
    | Extra scopes
    |--------------------------------------------------------------------------
    |
    | Standaard worden openid, profile en w_member_social aangevraagd (plus
    | w_organization_social wanneer er een organization_urn is ingesteld). Voeg
    | hier eventueel extra scopes toe.
    |
    */

    'scopes' => [],

    /*
    |--------------------------------------------------------------------------
    | Databasetabel
    |--------------------------------------------------------------------------
    */

    'table' => 'linkedin_accounts',

    /*
    |--------------------------------------------------------------------------
    | OAuth-routes
    |--------------------------------------------------------------------------
    |
    | De ingebouwde connect/callback-routes. Zet `enabled` op false als je de
    | OAuth-flow zelf wilt bedraden; gebruik dan `callback_name` om de service
    | naar jouw eigen callback-route te laten verwijzen (voor de redirect_uri).
    | `redirect_to` is de route waarheen na (mis)lukken wordt teruggestuurd.
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
    | Sessiesleutels
    |--------------------------------------------------------------------------
    |
    | Sleutels voor de CSRF-state en de flash-berichten na de OAuth-flow.
    |
    */

    'session' => [
        'state_key' => 'linkedin_oauth_state',
        'status_key' => 'linkedin_status',
        'error_key' => 'linkedin_error',
    ],

];
