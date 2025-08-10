<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_field_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('contact_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('contact_field_id')->constrained()->onDelete('cascade');
            $table->jsonb('value')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['contact_id', 'contact_field_id']);
            $table->unique(['contact_id', 'contact_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_field_values');
    }
};
