<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Floor plans for finished buildings (Blue Star, Akasya North/South…), same floor explorer as the upcoming projects:
 * a plan floor now belongs either to a property (as before) or to a building.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_floors', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->change();
            $table->foreignId('building_id')->nullable()->after('property_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_apartments')->nullable();
            $table->decimal('built_up_sqm', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        DB::table('property_floors')->whereNotNull('building_id')->delete();

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['total_apartments', 'built_up_sqm']);
        });

        Schema::table('property_floors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('building_id');
            $table->foreignId('property_id')->nullable(false)->change();
        });
    }
};
