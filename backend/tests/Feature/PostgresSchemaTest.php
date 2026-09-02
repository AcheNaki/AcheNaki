<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL-only schema guarantees. The default suite runs on SQLite, which silently
 * skips the CHECK constraints and never exercises PostgreSQL's implicit constraint
 * naming, so these assertions only run when the suite is pointed at PostgreSQL:
 *
 *   DB_CONNECTION=pgsql DB_HOST=… DB_DATABASE=… php artisan test
 */
class PostgresSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('These guarantees are specific to the production PostgreSQL driver.');
        }
    }

    /**
     * `$table->enum()` compiles to an inline PostgreSQL CHECK that the server names
     * `<table>_<column>_check`. A hand-written constraint reusing that exact name aborts
     * the whole migration with SQLSTATE 42710, which SQLite can never reveal.
     */
    public function test_domain_check_constraint_names_are_unique_per_table(): void
    {
        foreach ($this->domainTables() as $table) {
            $names = $this->checkConstraintNames($table);

            $this->assertSame(
                array_unique($names),
                $names,
                "Duplicate CHECK constraint name on {$table}; the migration cannot apply.",
            );
        }
    }

    public function test_domain_integrity_constraints_are_present(): void
    {
        $this->assertContains(
            'electricity_outage_events_lifecycle_state_check',
            $this->checkConstraintNames('electricity_outage_events'),
        );
        $this->assertContains(
            'utility_reports_status_compatibility_check',
            $this->checkConstraintNames('utility_reports'),
        );
        $this->assertContains(
            'utility_live_statuses_insufficient_check',
            $this->checkConstraintNames('utility_live_statuses'),
        );
        $this->assertContains(
            'gas_state_intervals_time_check',
            $this->checkConstraintNames('gas_state_intervals'),
        );
    }

    public function test_partial_unique_indexes_allow_only_one_open_record_per_locality(): void
    {
        foreach (['electricity_outage_events_one_open_index', 'gas_state_intervals_one_open_index'] as $index) {
            $definition = DB::scalar('select indexdef from pg_indexes where indexname = ?', [$index]);

            $this->assertNotNull($definition, "Missing partial unique index {$index}.");
            $this->assertStringContainsString('UNIQUE', (string) $definition);
            $this->assertStringContainsString('ended_at IS NULL', (string) $definition);
        }
    }

    /** @return list<string> */
    private function checkConstraintNames(string $table): array
    {
        return array_map(
            fn (object $row): string => $row->conname,
            DB::select(
                "select conname from pg_constraint
                 where conrelid = ?::regclass and contype = 'c'
                 order by conname",
                [$table],
            ),
        );
    }

    /** @return list<string> */
    private function domainTables(): array
    {
        return [
            'areas',
            'sub_areas',
            'anonymous_reporters',
            'utility_reports',
            'utility_live_statuses',
            'electricity_outage_events',
            'gas_state_intervals',
        ];
    }
}
