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
            $table->string('meta_id');
            $table->string('name', '512')->unique();
            $table->string('language');
            $table->enum('category', ['AUTHENTICATION', 'MARKETING', 'UTILITY']);
            $table->boolean('allow_category_change');

            $table->string('body', '1024');
            $table->json('body_example_variables')->nullable();
            $table->string('footer', '60')->nullable();

            $table->json('header')->nullable();
            $table->json('buttons')->nullable();

            $table->string('status')->default('PENDING');

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
