<?php

use App\Enums\BotAction;
use App\Enums\BotKeywordMatchType;
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
            $table->boolean('is_active')->default(true);

            $table->json('keywords')->nullable();
            $table->enum('trigger_type', BotTriggerType::values())->nullable();

            $table->enum('keyword_match_type', BotKeywordMatchType::values())->default(BotKeywordMatchType::EXACT->value);

            $table->integer('wait_time_minutes')->default(5);
            $table->enum('timeout_action', BotAction::values())->nullable();
            $table->ulid('timeout_assign_bot_id')->nullable();
            $table->foreignUlid('timeout_assign_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('timeout_message')->nullable();

            $table->text('no_match_message')->nullable();
            $table->enum('no_match_action', BotAction::values())->nullable();
            $table->ulid('no_match_assign_bot_id')->nullable();
            $table->foreignUlid('no_match_assign_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('end_conversation_action', BotAction::values())->nullable();
            $table->text('end_conversation_message')->nullable();
            $table->ulid('end_conversation_assign_bot_id')->nullable();
            $table->foreignUlid('end_conversation_assign_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('viewport')->nullable();

            $table->enum('default_no_match_action', BotAction::values())->nullable();
            $table->foreignUlid('default_no_match_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('default_no_match_bot_id')->nullable();

            $table->integer('default_timeout_minutes')->nullable();
            $table->enum('default_timeout_action', BotAction::values())->nullable();
            $table->foreignUlid('default_timeout_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('default_timeout_bot_id')->nullable();

            $table->integer('expire_warning_minutes')->nullable();
            $table->enum('default_expire_action', BotAction::values())->nullable();
            $table->text('default_expire_message')->nullable();
            $table->foreignUlid('default_expire_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('default_expire_bot_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('bots', function (Blueprint $table) {
            $table->foreign('timeout_assign_bot_id')->references('id')->on('bots')->nullOnDelete();
            $table->foreign('no_match_assign_bot_id')->references('id')->on('bots')->nullOnDelete();
            $table->foreign('end_conversation_assign_bot_id')->references('id')->on('bots')->nullOnDelete();
            $table->foreign('default_no_match_bot_id')->references('id')->on('bots')->nullOnDelete();
            $table->foreign('default_timeout_bot_id')->references('id')->on('bots')->nullOnDelete();
            $table->foreign('default_expire_bot_id')->references('id')->on('bots')->nullOnDelete();

            $table->index('is_active');
            $table->index('trigger_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
