<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('move_reports', function (Blueprint $table) {
            $table->string('oracle_property_id', 50)->nullable()->after('property_id');
            $table->string('property_name')->nullable()->after('oracle_property_id');
            $table->string('oracle_unit_id', 50)->nullable()->after('unit_label');
        });

        // Starting values carried over from the old WordPress plugin's settings.
        $seed = [
            'move_oracle_api_url' => 'https://gazelle-pleasant-anchovy.ngrok-free.app',
            'move_inspectors' => 'Jaseel',
        ];
        foreach ($seed as $key => $value) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'group' => 'move_in_out', 'type' => 'text', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('move_reports', function (Blueprint $table) {
            $table->dropColumn(['oracle_property_id', 'property_name', 'oracle_unit_id']);
        });
        DB::table('settings')->whereIn('key', ['move_oracle_api_url', 'move_inspectors'])->delete();
    }
};
