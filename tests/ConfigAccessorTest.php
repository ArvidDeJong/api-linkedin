<?php

declare(strict_types=1);

use Darvis\ApiLinkedin\Support\LinkedInConfig;

/**
 * LinkedInConfig is the one place that reads the package config. These tests guard the two things
 * that go wrong once a default is written down twice: an accessor that disagrees with the config
 * file, and a caller that reaches past the accessor and keeps its own stale fallback.
 */
function packageRoot(string $path = ''): string
{
    return dirname(__DIR__).($path === '' ? '' : '/'.$path);
}

it('returns the values the config file ships', function () {
    $config = require packageRoot('config/linkedin.php');

    expect(LinkedInConfig::apiVersion())->toBe($config['api_version'])
        ->and(LinkedInConfig::extraScopes())->toBe($config['scopes'])
        ->and(LinkedInConfig::organizationsEnabled())->toBe($config['organizations']['enabled'])
        ->and(LinkedInConfig::organizationsCacheTtl())->toBe((int) $config['organizations']['cache_ttl'])
        ->and(LinkedInConfig::table())->toBe($config['table'])
        ->and(LinkedInConfig::routesEnabled())->toBe($config['routes']['enabled'])
        ->and(LinkedInConfig::routePrefix())->toBe($config['routes']['prefix'])
        ->and(LinkedInConfig::routeMiddleware())->toBe($config['routes']['middleware'])
        ->and(LinkedInConfig::connectRouteName())->toBe($config['routes']['connect_name'])
        ->and(LinkedInConfig::callbackRouteName())->toBe($config['routes']['callback_name'])
        ->and(LinkedInConfig::redirectTo())->toBe($config['routes']['redirect_to'])
        ->and(LinkedInConfig::stateKey())->toBe($config['session']['state_key'])
        ->and(LinkedInConfig::scopesKey())->toBe($config['session']['scopes_key'])
        ->and(LinkedInConfig::statusKey())->toBe($config['session']['status_key'])
        ->and(LinkedInConfig::errorKey())->toBe($config['session']['error_key']);
});

it('follows a changed setting', function () {
    config([
        'linkedin.api_version' => '209912',
        'linkedin.routes.prefix' => 'social/linkedin',
        'linkedin.organizations.cache_ttl' => 0,
        'linkedin.session.state_key' => 'my_state',
    ]);

    expect(LinkedInConfig::apiVersion())->toBe('209912')
        ->and(LinkedInConfig::routePrefix())->toBe('social/linkedin')
        ->and(LinkedInConfig::organizationsCacheTtl())->toBe(0)
        ->and(LinkedInConfig::stateKey())->toBe('my_state');
});

it('treats a credential that is left empty as not configured at all', function () {
    config([
        'linkedin.client_id' => '',
        'linkedin.client_secret' => '',
        'linkedin.organization_urn' => null,
    ]);

    expect(LinkedInConfig::clientId())->toBeNull()
        ->and(LinkedInConfig::clientSecret())->toBeNull()
        ->and(LinkedInConfig::organizationUrn())->toBeNull();

    config([
        'linkedin.client_id' => 'abc',
        'linkedin.organization_urn' => 'urn:li:organization:1',
    ]);

    expect(LinkedInConfig::clientId())->toBe('abc')
        ->and(LinkedInConfig::organizationUrn())->toBe('urn:li:organization:1');
});

it('gives the extra scopes and the route middleware as plain lists', function () {
    config([
        'linkedin.scopes' => [3 => 'r_events', 7 => 'rw_events'],
        'linkedin.routes.middleware' => ['web', 'auth'],
    ]);

    expect(LinkedInConfig::extraScopes())->toBe(['r_events', 'rw_events'])
        ->and(LinkedInConfig::routeMiddleware())->toBe(['web', 'auth']);
});

it('is the only place in the package that reads the config', function () {
    $offenders = [];

    foreach (['src', 'resources', 'routes'] as $directory) {
        $path = packageRoot($directory);

        if (! is_dir($path)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(packageRoot('').'/', '', $file->getPathname());

            if (str_contains($relative, 'LinkedInConfig.php')) {
                continue;
            }

            if (preg_match("/config\(['\"]linkedin\./", (string) file_get_contents($file->getPathname()))) {
                $offenders[] = $relative;
            }
        }
    }

    expect($offenders)->toBe([], 'these read the config directly instead of through LinkedInConfig');
});

it('keeps the config keys in alphabetical order, in every group', function () {
    $lines = file(packageRoot('config/linkedin.php'), FILE_IGNORE_NEW_LINES);

    $top = [];
    $groups = [];
    $current = null;

    foreach ($lines as $line) {
        if (preg_match("/^    '([a-z_0-9]+)' =>/", $line, $match)) {
            $top[] = $match[1];
            $current = $match[1];
            $groups[$current] = [];

            continue;
        }

        if ($current !== null && preg_match("/^        '([a-z_0-9]+)' =>/", $line, $match)) {
            $groups[$current][] = $match[1];
        }
    }

    $sortedTop = $top;
    sort($sortedTop);

    expect($top)->toBe($sortedTop, 'the groups are not in alphabetical order');

    foreach ($groups as $group => $keys) {
        $sorted = $keys;
        sort($sorted);

        expect($keys)->toBe($sorted, "the keys in '{$group}' are not in alphabetical order");
    }
});
