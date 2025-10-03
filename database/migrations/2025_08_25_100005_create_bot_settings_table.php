<?php

use App\Enums\BotAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global tenant-level bot settings
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->unique();

            // Working hours configuration - used by working_hours node type
            $table->boolean('enable_working_hours')->default(false);
            $table->json('working_hours')->nullable();

            $table->enum('default_no_match_action', BotAction::values())->default(BotAction::UNASSIGN->value);
            $table->foreignUlid('default_no_match_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('default_no_match_bot_id')->nullable()->constrained('bots')->nullOnDelete();

            // Default timeout action (overridden by individual bot config)
            $table->integer('default_timeout_minutes')->default(10);
            $table->enum('default_timeout_action', BotAction::values())->default(BotAction::UNASSIGN->value);
            $table->foreignUlid('default_timeout_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('default_timeout_bot_id')->nullable()->constrained('bots')->nullOnDelete();

            $table->integer('expire_warning_hours')->default(1);
            $table->enum('default_expire_action', BotAction::values())->default(BotAction::MESSAGE->value);
            $table->text('default_expire_message')->nullable();
            $table->foreignUlid('default_expire_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('default_expire_bot_id')->nullable()->constrained('bots')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
