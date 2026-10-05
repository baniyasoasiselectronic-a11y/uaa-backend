<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('move_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type', 20); // move_in | move_out
            $table->date('report_date');
            $table->string('tenant_name');
            $table->string('tenant_phone')->nullable();
            $table->string('tenant_email')->nullable();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_label')->nullable();
            $table->string('unit_type')->nullable();
            $table->string('inspector')->nullable();
            $table->string('electricity_reading')->nullable();
            $table->string('water_reading')->nullable();
            $table->unsignedSmallInteger('keys_count')->nullable();
            $table->text('notes')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });

        Schema::create('move_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('move_report_id')->constrained()->cascadeOnDelete();
            $table->string('area')->nullable();
            $table->string('description');
            $table->string('condition', 30)->nullable();
            $table->string('remarks')->nullable();
            $table->decimal('charge', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('move_report_items');
        Schema::dropIfExists('move_reports');
    }
};
