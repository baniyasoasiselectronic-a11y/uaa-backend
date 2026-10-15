<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Everything needed to send a complaint to Oracle and to see whether it arrived. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('oracle_unit_id', 50)->nullable()->after('oracle_property_id');
            $table->string('unit_type_code', 10)->nullable()->after('unit_type');
            $table->string('complaint_type_code', 10)->nullable()->after('category');
            $table->string('oracle_status', 20)->nullable()->index()->after('requisition_number'); // posted | pending | manual
            $table->unsignedTinyInteger('oracle_attempts')->default(0)->after('oracle_status');
            $table->string('oracle_last_error')->nullable()->after('oracle_attempts');
        });

        // complaints carried over from the old site were already in Oracle
        DB::table('complaints')->whereNotNull('legacy_wp_id')->update(['oracle_status' => 'posted']);
    }

    public function down(): void
    {
        Schema::table('complaints', fn (Blueprint $t) => $t->dropColumn(['oracle_unit_id', 'unit_type_code', 'complaint_type_code', 'oracle_status', 'oracle_attempts', 'oracle_last_error']));
    }
};
