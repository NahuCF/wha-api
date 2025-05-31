<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', '512')->unique();
            $table->string('language');
            $table->string('category');

            $table->string('body', '1024');
            $table->json('body_example_variables')->nullable();
            $table->string('footer', '60')->nullable();

            // Header
            $table->enum('header_type', ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'])->nullable();
            $table->string('header_text')->nullable();
            $table->string('header_media_url')->nullable();

            $table->string('status')->default('DRAFT');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
