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
        Schema::create('password_set_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email');
            $table->string('tenant_id');
            $table->ulid('user_id');
            $table->string('token')->unique();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'tenant_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_set_tokens');
    }
};
