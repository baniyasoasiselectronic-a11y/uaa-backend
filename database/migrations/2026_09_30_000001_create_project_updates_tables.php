<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->date('period_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('project_update_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_update_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_update_images');
        Schema::dropIfExists('project_updates');
    }
};
