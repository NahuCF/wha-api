<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('template_id');
            $table->string('name');
            $table->json('recipients')->nullable();
            $table->json('groups')->nullable();
            $table->json('variables')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'])->default('draft');
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
