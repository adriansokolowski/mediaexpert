<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A guard rail rather than a business rule.
 *
 * RefreshDatabase drops and recreates every table, so the suite must never be
 * pointed at a real database. That is easy to get wrong: PHPUnit's <env> entries
 * do not override variables that already exist in the environment unless they
 * are marked force="true", and the Docker container does export DB_CONNECTION.
 * If somebody removes those flags, this test fails instead of the developer's
 * data disappearing.
 */
final class TestEnvironmentTest extends TestCase
{
    public function test_the_suite_runs_against_a_throwaway_in_memory_database(): void
    {
        $connection = DB::connection();

        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
    }

    public function test_the_application_is_in_the_testing_environment(): void
    {
        $this->assertTrue($this->app->environment('testing'));
        $this->assertFalse($this->app->isProduction());
    }
}
