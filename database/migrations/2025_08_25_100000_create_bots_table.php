<?php

use App\Enums\BotAction;
use App\Enums\BotTriggerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // bot configuration with triggers, timeouts, and flow settings
        Schema::create('bots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained();
            $table->foreignUlid('updated_user_id')->constrained('users');

            $table->string('name');
            $table->enum('trigger_type', BotTriggerType::values())->nullable();

            $table->json('keywords')->nullable();

            // Analytics columns
            $table->integer('total_sessions')->default(0);
            $table->integer('completed_sessions')->default(0);
            $table->integer('abandoned_sessions')->default(0);

            // Time to wait for the user response
            $table->integer('wait_time_minutes')->default(5);
            $table->enum('timeout_action', BotAction::values())->nullable();
            $table->ulid('timeout_assign_bot_id')->nullable();
            $table->foreignUlid('timeout_assign_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('timeout_message')->nullable();

            // Response of user do not match anything
            $table->enum('no_match_action', BotAction::values())->nullable();
            $table->ulid('no_match_assign_bot_id')->nullable();
            $table->foreignUlid('no_match_assign_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('no_match_message')->nullable();

            // Conversation finish
            $table->enum('end_conversation_action', BotAction::values())->nullable();
            $table->text('end_conversation_message')->nullable();
            $table->ulid('end_conversation_assign_bot_id')->nullable();
            $table->foreignUlid('end_conversation_assign_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('viewport')->nullable();

            $table->timestamps();
        });

        Schema::table('bots', function (Blueprint $table) {
            $table->foreign('timeout_assign_bot_id')->references('id')->on('bots')->nullOnDelete();
            $table->foreign('no_match_assign_bot_id')->references('id')->on('bots')->nullOnDelete();
            $table->foreign('end_conversation_assign_bot_id')->references('id')->on('bots')->nullOnDelete();

            $table->index('trigger_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
