<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('name_bn', 120)->nullable();
            $table->string('slug', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['area_id', 'name']);
            $table->unique(['area_id', 'slug']);
            $table->index(['area_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_areas');
    }
};
