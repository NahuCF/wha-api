<?php

use App\Enums\BotSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Active chat sessions tracking state and variables
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUlid('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('contact_id')->constrained()->cascadeOnDelete();

            $table->string('current_node_id')->nullable();  // Library id
            $table->enum('status', BotSessionStatus::values())->default(BotSessionStatus::ACTIVE->value);
            $table->json('variables')->nullable();
            $table->json('history')->nullable();

            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('timeout_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('timeout_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};
