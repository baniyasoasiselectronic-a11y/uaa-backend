<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields needed to carry over what the old uaa.ae WordPress site really used:
 * tenant accounts (with a "no complaint fee" flag instead of the Administrator
 * role), maintenance complaints (number, Oracle requisition, visit slot, payment)
 * and the inspection reports (so imported entries can be told apart).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('complaint_fee_exempt')->default(false)->after('locale');
            $table->unsignedBigInteger('legacy_wp_id')->nullable()->unique()->after('complaint_fee_exempt');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_wp_id')->nullable()->unique();
            $table->string('complaint_number', 60)->nullable()->index();
            $table->string('requisition_number', 60)->nullable()->index();
            $table->string('oracle_property_id', 50)->nullable();
            $table->string('property_name')->nullable();
            $table->string('unit_type', 60)->nullable();
            $table->string('unit_label', 60)->nullable();
            $table->date('visit_date')->nullable();
            $table->string('visit_time_range', 60)->nullable();
            $table->string('payment_status', 20)->default('not_required')->index();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->string('payment_ref')->nullable();
        });

        Schema::table('move_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_entry_id')->nullable()->unique();
            $table->timestamp('emailed_at')->nullable();
        });

        Schema::table('technician_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_entry_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('technician_reports', fn (Blueprint $t) => $t->dropColumn('legacy_entry_id'));
        Schema::table('move_reports', fn (Blueprint $t) => $t->dropColumn(['legacy_entry_id', 'emailed_at']));
        Schema::table('complaints', fn (Blueprint $t) => $t->dropColumn([
            'legacy_wp_id', 'complaint_number', 'requisition_number', 'oracle_property_id', 'property_name',
            'unit_type', 'unit_label', 'visit_date', 'visit_time_range', 'payment_status', 'payment_amount', 'payment_ref',
        ]));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['complaint_fee_exempt', 'legacy_wp_id']));
    }
};
