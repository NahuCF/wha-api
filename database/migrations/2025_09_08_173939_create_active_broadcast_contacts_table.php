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
        Schema::create('active_broadcast_contacts', function (Blueprint $table) {
            $table->foreignUlid('contact_id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->onDelete('cascade');
            $table->integer('broadcast_count')->default(0);
            $table->jsonb('broadcast_ids')->nullable();
            $table->timestamp('updated_at');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_broadcast_contacts');
    }
};
