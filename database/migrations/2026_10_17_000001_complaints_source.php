<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Shows in the admin where each complaint came from: this website, or the old WordPress site / Flutter app. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->index()->after('oracle_status'); // website | wordpress
        });
        DB::table('complaints')->whereNotNull('legacy_wp_id')->update(['source' => 'wordpress']);
        DB::table('complaints')->whereNull('legacy_wp_id')->update(['source' => 'website']);
    }

    public function down(): void
    {
        Schema::table('complaints', fn (Blueprint $t) => $t->dropColumn('source'));
    }
};
