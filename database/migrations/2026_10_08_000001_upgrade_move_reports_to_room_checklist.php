<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('move_reports', function (Blueprint $table) {
            $table->string('contract_no')->nullable()->after('reference');
            $table->string('beds', 50)->nullable()->after('unit_type');
            $table->unsignedSmallInteger('parking_cards')->nullable()->after('keys_count');
            $table->json('rooms')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->longText('tenant_signature')->nullable();
            $table->longText('inspector_signature')->nullable();
        });

        // Charges now live inside the per-room checklist (`rooms` JSON).
        Schema::dropIfExists('move_report_items');
    }

    public function down(): void
    {
        Schema::table('move_reports', function (Blueprint $table) {
            $table->dropColumn(['contract_no', 'beds', 'parking_cards', 'rooms', 'subtotal', 'vat_amount', 'total_amount', 'tenant_signature', 'inspector_signature']);
        });
    }
};
