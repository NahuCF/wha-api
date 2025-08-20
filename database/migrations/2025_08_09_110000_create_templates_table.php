<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->onDelete('cascade');
            $table->string('meta_id')->nullable();
            $table->foreignUlid('waba_id')->constrained();
            $table->string('name', '512');
            $table->string('language');
            $table->enum('category', ['AUTHENTICATION', 'MARKETING', 'UTILITY']);
            $table->boolean('allow_category_change');

            $table->string('body', '1024');
            $table->json('body_example_variables')->nullable();
            $table->string('footer', '60')->nullable();

            $table->json('header')->nullable();
            $table->json('buttons')->nullable();

            $table->string('status')->default('PENDING');
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
