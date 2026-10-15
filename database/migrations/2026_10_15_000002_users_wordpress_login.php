<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lets tenants brought over from WordPress sign in with their old password and username. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wp_login', 100)->nullable()->index()->after('legacy_wp_id');
            $table->string('wp_role', 40)->nullable()->after('wp_login');
            $table->text('wp_password_hash')->nullable()->after('wp_role'); // cleared at the first sign-in
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['wp_login', 'wp_role', 'wp_password_hash']));
    }
};
