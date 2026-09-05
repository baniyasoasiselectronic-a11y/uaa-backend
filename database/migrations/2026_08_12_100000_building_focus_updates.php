<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->decimal('average_price', 14, 2)->nullable()->after('floors_count');
            $table->string('main_image')->nullable()->after('average_price');
        });

        Schema::create('building_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreignId('building_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('building_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('building_id');
        });
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('building_id');
        });
        Schema::dropIfExists('building_images');
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['average_price', 'main_image']);
        });
    }
};
