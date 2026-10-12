<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the admin Enquiries inbox also hold the quick contacts that never go through a form:
 * taps on the WhatsApp / call icons and what visitors ask the website's AI assistant.
 * `session_key` groups one visitor's assistant chat into a single enquiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('session_key', 64)->nullable()->index()->after('source');
            $table->string('page')->nullable()->after('session_key');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn(['session_key', 'page']);
        });
    }
};
