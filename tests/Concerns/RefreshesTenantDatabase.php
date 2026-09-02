<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Drop-in replacement for Laravel's RefreshDatabase trait.
 *
 * Tests run against the plain "mysql" connection (see phpunit.xml) — there is
 * no real tenant to switch to. The tenant application tables (roles,
 * permissions, users, divisions, ...) only exist via the migrations in
 * database/migrations/tenant/ (the campus schema baseline), so that path
 * must always be included alongside the default central-plane migrations
 * whenever the test database is refreshed.
 *
 * PHP resolves a trait's own methods before any inherited parent method, so
 * overriding migrateFreshUsing() on Tests\TestCase would silently be ignored
 * by any test class that also `use`s RefreshDatabase directly. Overriding it
 * here instead — in the trait actually consumed by test classes — works,
 * because this trait's own migrateFreshUsing() takes precedence over the
 * nested RefreshDatabase trait's version.
 *
 * Only the tenant path is migrated (not database/migrations) — the central
 * migrations create a `sessions` table too, which collides with the one in
 * the tenant SQL baseline when both run against a single flat test schema.
 * No test in this suite exercises central-plane behaviour, so this is safe.
 */
trait RefreshesTenantDatabase
{
    use RefreshDatabase {
        migrateFreshUsing as protected baseMigrateFreshUsing;
    }

    protected function migrateFreshUsing()
    {
        return array_merge($this->baseMigrateFreshUsing(), [
            '--path' => [
                'database/migrations/tenant',
            ],
        ]);
    }
}
