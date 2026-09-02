<?php

use App\Enums\CityCorporation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('name_bn', 120)->nullable();
            $table->string('slug', 120)->unique();
            $table->enum('city_corporation', array_column(CityCorporation::cases(), 'value'));
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['city_corporation', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
