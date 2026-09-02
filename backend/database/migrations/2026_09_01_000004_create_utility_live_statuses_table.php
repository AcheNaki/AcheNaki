<?php

use App\Enums\ConfidenceLevel;
use App\Enums\LiveStatus;
use App\Enums\UtilityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_areas', function (Blueprint $table): void {
            $table->unique(['id', 'area_id'], 'sub_areas_id_area_unique');
        });

        Schema::table('utility_reports', function (Blueprint $table): void {
            $table->index(
                ['sub_area_id', 'utility_type', 'anonymous_reporter_id', 'reported_at'],
                'utility_reports_latest_reporter_evidence_index',
            );
        });

        Schema::create('utility_live_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sub_area_id');
            $table->enum('utility_type', array_column(UtilityType::cases(), 'value'));
            $table->enum('estimated_status', array_column(LiveStatus::cases(), 'value'));
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->enum('confidence_level', array_column(ConfidenceLevel::cases(), 'value'))->nullable();
            $table->unsignedInteger('recent_report_count')->default(0);
            $table->unsignedInteger('supporting_report_count')->default(0);
            $table->unsignedInteger('contradicting_report_count')->default(0);
            $table->timestampTz('status_since')->nullable();
            $table->timestampTz('evidence_window_started_at');
            $table->timestampTz('last_report_at')->nullable();
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            $table->foreign(['sub_area_id', 'area_id'])
                ->references(['id', 'area_id'])
                ->on('sub_areas')
                ->restrictOnDelete();
            $table->unique(['sub_area_id', 'utility_type']);
            $table->index(['utility_type', 'estimated_status', 'last_report_at']);
            $table->index(['area_id', 'utility_type', 'last_report_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE utility_live_statuses ADD CONSTRAINT utility_live_statuses_status_compatibility_check CHECK ((utility_type = 'ELECTRICITY' AND estimated_status IN ('AVAILABLE', 'UNAVAILABLE', 'UNSTABLE', 'MIXED', 'INSUFFICIENT_DATA')) OR (utility_type = 'GAS' AND estimated_status IN ('NORMAL', 'LOW', 'VERY_LOW', 'UNAVAILABLE', 'MIXED', 'INSUFFICIENT_DATA'))) ");
            DB::statement('ALTER TABLE utility_live_statuses ADD CONSTRAINT utility_live_statuses_score_check CHECK (confidence_score BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE utility_live_statuses ADD CONSTRAINT utility_live_statuses_counts_check CHECK (supporting_report_count + contradicting_report_count <= recent_report_count)');
            DB::statement("ALTER TABLE utility_live_statuses ADD CONSTRAINT utility_live_statuses_insufficient_check CHECK ((estimated_status = 'INSUFFICIENT_DATA' AND confidence_score = 0 AND confidence_level IS NULL) OR estimated_status <> 'INSUFFICIENT_DATA')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_live_statuses');

        Schema::table('utility_reports', function (Blueprint $table): void {
            $table->dropIndex('utility_reports_latest_reporter_evidence_index');
        });

        Schema::table('sub_areas', function (Blueprint $table): void {
            $table->dropUnique('sub_areas_id_area_unique');
        });
    }
};
