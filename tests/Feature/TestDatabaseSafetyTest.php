<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TestDatabaseSafetyTest extends TestCase
{
    #[DataProvider('unsafeDatabaseConfigurations')]
    public function test_refuses_to_run_against_an_unsafe_database(array $configuration): void
    {
        $this->app['config']->set($configuration);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tests require SQLite :memory:');

        $this->assertIsolatedTestDatabase($this->app);
    }

    public static function unsafeDatabaseConfigurations(): array
    {
        return [
            'mysql connection' => [['database.default' => 'mysql']],
            'sqlite file' => [['database.connections.sqlite.database' => 'database/database.sqlite']],
            'connection URL override' => [['database.connections.sqlite.url' => 'mysql://localhost/working_database']],
        ];
    }
}
