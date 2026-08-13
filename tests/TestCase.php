<?php

namespace ErnestoChapon\UserAuditable\Tests;

use ErnestoChapon\UserAuditable\Providers\UserAuditableServiceProvider;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestUser;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            UserAuditableServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Use environment configuration, fallback to SQLite for CI
        $connection = env('DB_CONNECTION', 'sqlite');

        if ($connection === 'mysql') {
            config()->set('database.default', 'mysql');
            config()->set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => env('TEST_DB_HOST', '127.0.0.1'),
                'port' => env('TEST_DB_PORT', '3306'),
                'database' => env('TEST_DB_DATABASE', 'test_database'),
                'username' => env('TEST_DB_USERNAME', 'root'),
                'password' => env('TEST_DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ]);
        } else {
            // Default to SQLite for CI
            config()->set('database.default', 'sqlite');
            config()->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }

        // Authentication configuration
        config()->set('auth.defaults.guard', 'web');
        config()->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        config()->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);
    }
}
