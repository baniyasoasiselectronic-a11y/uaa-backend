<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('plan_image')->nullable();
            $table->unsignedSmallInteger('total_apartments')->nullable();
            $table->decimal('total_area_sqm', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('property_floor_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_floor_id')->constrained()->cascadeOnDelete();
            $table->string('unit_type', 50);
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->decimal('suite_sqm', 8, 2)->nullable();
            $table->string('outdoor_label', 30)->default('Balcony');
            $table->decimal('outdoor_sqm', 8, 2)->nullable();
            $table->string('image')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_floor_units');
        Schema::dropIfExists('property_floors');
    }
};
