<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $this->assertIsolatedTestDatabase($app);

        return $app;
    }

    protected function assertIsolatedTestDatabase(Application $app): void
    {
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || ! empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('Tests require SQLite :memory: without a database URL. Check the test environment and cached configuration.');
        }
    }
}
