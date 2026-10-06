<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('beds', 50)->nullable();
            $table->string('tenant_name');
            $table->string('tenant_phone')->nullable();
            $table->string('tenant_email')->nullable();
            $table->string('oracle_property_id', 50)->nullable();
            $table->string('property_name')->nullable();
            $table->string('unit_label')->nullable();
            $table->string('oracle_unit_id', 50)->nullable();
            $table->string('unit_type')->nullable();
            $table->string('contract_file', 600)->nullable();
            $table->longText('tenant_signature')->nullable();
            $table->string('renewed_by')->nullable();
            $table->longText('renewed_by_signature')->nullable();
            $table->boolean('imported')->default(false);
            $table->timestamps();
        });

        Schema::create('technician_reports', function (Blueprint $table) {
            $table->id();
            $table->string('technician_name');
            $table->string('technician_code')->nullable();
            $table->date('report_date');
            $table->json('entries')->nullable();
            $table->boolean('imported')->default(false);
            $table->timestamps();
        });

        if (! DB::table('settings')->where('key', 'renewal_staff')->exists()) {
            DB::table('settings')->insert(['key' => 'renewal_staff', 'value' => "Yousaf\nLamis", 'group' => 'move_in_out', 'type' => 'text', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_reports');
        Schema::dropIfExists('contract_renewals');
        DB::table('settings')->where('key', 'renewal_staff')->delete();
    }
};
