<?php

namespace Darvis\ApiLinkedin\Tests;

use Darvis\ApiLinkedin\LinkedInServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LinkedInServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('linkedin.client_id', 'client-id');
        $app['config']->set('linkedin.client_secret', 'client-secret');
        $app['config']->set('linkedin.organization_urn', 'urn:li:organization:42');
        $app['config']->set('linkedin.api_version', '202601');
    }
}
