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
        $app['config']->set('truelist.raise_on_error', false);
        $app['config']->set('truelist.cache.enabled', false);
        $app['config']->set('truelist.cache.ttl', 3600);
        $app['config']->set('truelist.cache.prefix', 'truelist:');
    }

    protected function apiResponse(array $overrides = []): array
    {
        return [
            'emails' => [
                array_merge([
                    'address' => 'user@example.com',
                    'domain' => 'example.com',
                    'canonical' => 'user',
                    'mx_record' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'email_state' => 'ok',
                    'email_sub_state' => 'email_ok',
                    'verified_at' => '2026-02-21T10:00:00.000Z',
                    'did_you_mean' => null,
                ], $overrides),
            ],
        ];
    }
}
