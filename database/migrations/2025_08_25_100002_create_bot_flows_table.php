<?php

use App\Enums\FlowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_flows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bot_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', FlowStatus::values())->default(FlowStatus::DRAFT->value);

            // Analytics columns
            $table->integer('total_sessions')->default(0);
            $table->integer('completed_sessions')->default(0);
            $table->integer('abandoned_sessions')->default(0);

            // Track who created and modified
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('updated_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['bot_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_flows');
    }
};
