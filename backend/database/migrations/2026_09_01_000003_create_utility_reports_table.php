<?php

use App\Enums\ElectricityStatus;
use App\Enums\GasStatus;
use App\Enums\TimeBucket;
use App\Enums\UtilityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = array_values(array_unique([
            ...array_column(ElectricityStatus::cases(), 'value'),
            ...array_column(GasStatus::cases(), 'value'),
        ]));

        Schema::create('utility_reports', function (Blueprint $table) use ($statuses): void {
            $table->id();
            $table->foreignId('anonymous_reporter_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_area_id')->constrained()->restrictOnDelete();
            $table->enum('utility_type', array_column(UtilityType::cases(), 'value'));
            $table->enum('status', $statuses);
            $table->enum('time_bucket', array_column(TimeBucket::cases(), 'value'));
            $table->timestampTz('reported_at');
            $table->timestampTz('estimated_started_at')->nullable();
            $table->boolean('can_cook')->nullable();
            $table->timestampsTz();

            $table->index(['sub_area_id', 'utility_type', 'reported_at']);
            $table->index(['utility_type', 'reported_at']);
            $table->index(['anonymous_reporter_id', 'reported_at']);
            $table->index(
                ['anonymous_reporter_id', 'sub_area_id', 'utility_type', 'status', 'reported_at'],
                'utility_reports_duplicate_lookup_index',
            );
        });

        $compatibility = "((utility_type = 'ELECTRICITY' AND status IN ('AVAILABLE', 'UNAVAILABLE', 'UNSTABLE')) OR (utility_type = 'GAS' AND status IN ('NORMAL', 'LOW', 'VERY_LOW', 'UNAVAILABLE')))";
        $gasContext = "(utility_type = 'GAS' OR can_cook IS NULL)";

        if (DB::getDriverName() === 'sqlite') {
            $sqliteCompatibility = str_replace(['utility_type', 'status'], ['NEW.utility_type', 'NEW.status'], $compatibility);
            $sqliteGasContext = str_replace(['utility_type', 'can_cook'], ['NEW.utility_type', 'NEW.can_cook'], $gasContext);
            DB::statement("CREATE TRIGGER utility_reports_validate_insert BEFORE INSERT ON utility_reports WHEN NOT {$sqliteCompatibility} OR NOT {$sqliteGasContext} BEGIN SELECT RAISE(ABORT, 'invalid utility report domain values'); END");
            DB::statement("CREATE TRIGGER utility_reports_validate_update BEFORE UPDATE ON utility_reports WHEN NOT {$sqliteCompatibility} OR NOT {$sqliteGasContext} BEGIN SELECT RAISE(ABORT, 'invalid utility report domain values'); END");
        } else {
            DB::statement("ALTER TABLE utility_reports ADD CONSTRAINT utility_reports_status_compatibility_check CHECK ({$compatibility})");
            DB::statement("ALTER TABLE utility_reports ADD CONSTRAINT utility_reports_gas_context_check CHECK ({$gasContext})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_reports');
    }
};
