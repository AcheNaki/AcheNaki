<?php

use App\Enums\ConfidenceLevel;
use App\Enums\ElectricityOutageLifecycle;
use App\Enums\GasStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electricity_outage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sub_area_id');
            $table->enum('lifecycle', array_column(ElectricityOutageLifecycle::cases(), 'value'));
            $table->timestampTz('started_at');
            $table->timestampTz('first_supported_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('resolution_candidate_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->enum('start_confidence_level', array_column(ConfidenceLevel::cases(), 'value'));
            $table->enum('end_confidence_level', array_column(ConfidenceLevel::cases(), 'value'))->nullable();
            $table->unsignedSmallInteger('inference_version');
            $table->timestampsTz();

            $table->foreign(['sub_area_id', 'area_id'])
                ->references(['id', 'area_id'])
                ->on('sub_areas')
                ->restrictOnDelete();
            $table->index(['sub_area_id', 'started_at']);
            $table->index(['sub_area_id', 'ended_at']);
        });

        Schema::create('gas_state_intervals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sub_area_id');
            $table->enum('status', array_column(GasStatus::cases(), 'value'));
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampTz('observed_until_at');
            $table->enum('start_confidence_level', array_column(ConfidenceLevel::cases(), 'value'));
            $table->enum('pending_status', array_column(GasStatus::cases(), 'value'))->nullable();
            $table->enum('pending_confidence_level', array_column(ConfidenceLevel::cases(), 'value'))->nullable();
            $table->timestampTz('pending_since')->nullable();
            $table->unsignedSmallInteger('inference_version');
            $table->timestampsTz();

            $table->foreign(['sub_area_id', 'area_id'])
                ->references(['id', 'area_id'])
                ->on('sub_areas')
                ->restrictOnDelete();
            $table->index(['sub_area_id', 'started_at']);
            $table->index(['sub_area_id', 'ended_at']);
        });

        DB::statement('CREATE UNIQUE INDEX electricity_outage_events_one_open_index ON electricity_outage_events (sub_area_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX gas_state_intervals_one_open_index ON gas_state_intervals (sub_area_id) WHERE ended_at IS NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE electricity_outage_events ADD CONSTRAINT electricity_outage_events_time_check CHECK (ended_at IS NULL OR ended_at >= started_at)');
            // PostgreSQL implicitly names the inline enum CHECK generated for the `lifecycle`
            // column `electricity_outage_events_lifecycle_check`, so this lifecycle-integrity
            // constraint must use a distinct name to avoid a duplicate-object migration failure.
            DB::statement("ALTER TABLE electricity_outage_events ADD CONSTRAINT electricity_outage_events_lifecycle_state_check CHECK ((lifecycle = 'CANDIDATE' AND confirmed_at IS NULL AND ended_at IS NULL) OR (lifecycle = 'ACTIVE' AND confirmed_at IS NOT NULL AND ended_at IS NULL) OR (lifecycle = 'RESOLVED' AND confirmed_at IS NOT NULL AND ended_at IS NOT NULL))");
            DB::statement('ALTER TABLE gas_state_intervals ADD CONSTRAINT gas_state_intervals_time_check CHECK ((ended_at IS NULL OR ended_at >= started_at) AND observed_until_at >= started_at)');
            DB::statement('ALTER TABLE gas_state_intervals ADD CONSTRAINT gas_state_intervals_pending_check CHECK ((pending_status IS NULL AND pending_confidence_level IS NULL AND pending_since IS NULL) OR (pending_status IS NOT NULL AND pending_confidence_level IS NOT NULL AND pending_since IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_state_intervals');
        Schema::dropIfExists('electricity_outage_events');
    }
};
