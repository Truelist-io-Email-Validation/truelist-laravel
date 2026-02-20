<?php

namespace Truelist\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Truelist\TruelistServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TruelistServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Truelist' => \Truelist\Facades\Truelist::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('truelist.api_key', 'test_api_key');
        $app['config']->set('truelist.base_url', 'https://api.truelist.io');
        $app['config']->set('truelist.timeout', 10);
        $app['config']->set('truelist.allow_risky', true);
        $app['config']->set('truelist.raise_on_error', false);
        $app['config']->set('truelist.cache.enabled', false);
        $app['config']->set('truelist.cache.ttl', 3600);
        $app['config']->set('truelist.cache.prefix', 'truelist:');
    }
}
