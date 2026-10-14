<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Expected completion (handover) date for upcoming projects, shown instead of a price on the website. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->date('completion_date')->nullable()->after('status');
        });

        DB::table('properties')->whereIn('slug', ['akasya-east', 'akasya-west'])->update(['completion_date' => '2026-12-31']);
        DB::table('properties')->whereIn('slug', ['jeddaf', 'jeddaf-star'])->update(['completion_date' => '2027-12-31']);
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('completion_date'));
    }
};
