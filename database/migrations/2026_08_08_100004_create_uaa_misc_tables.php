<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_with_us_requests', function (Blueprint $table) {
            $table->id();
            $table->string('owner_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('property_type')->nullable();
            $table->string('location')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->decimal('expected_price', 14, 2)->nullable();
            $table->string('document_path')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['new', 'reviewing', 'approved', 'rejected'])->default('new')->index();
            $table->timestamps();
        });

        Schema::create('callback_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('preferred_time')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['new', 'contacted', 'closed'])->default('new')->index();
            $table->timestamps();
        });

        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['new', 'read', 'replied', 'closed'])->default('new')->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('string');
            $table->timestamps();
        });

        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('locale', 5)->default('en');
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image')->nullable();
            $table->json('schema')->nullable();
            $table->timestamps();
            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_unique_per_locale');
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('locale', 5);
            $table->string('field');
            $table->longText('value')->nullable();
            $table->timestamps();
            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'translation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('seo_metadata');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('contact_requests');
        Schema::dropIfExists('callback_requests');
        Schema::dropIfExists('list_with_us_requests');
    }
};
