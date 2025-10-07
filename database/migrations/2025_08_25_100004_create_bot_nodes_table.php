<?php

use App\Enums\AssignType;
use App\Enums\BotNodeHeaderType;
use App\Enums\BotNodeType;
use App\Enums\ComparisonOperator;
use App\Enums\MediaType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Individual flow nodes with position and configuration
        Schema::create('bot_nodes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bot_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->enum('type', BotNodeType::values());
            $table->string('label')->nullable();

            $table->float('position_x')->default(0);
            $table->float('position_y')->default(0);

            $table->json('data')->nullable();

            // Message/Template/Media nodes
            $table->text('content')->nullable();
            $table->string('media_url')->nullable(); // S3 URL for media
            $table->enum('media_type', MediaType::values())->nullable();

            $table->enum('header_type', BotNodeHeaderType::values())->nullable();
            $table->text('header_text')->nullable()->after('header_type');
            $table->string('header_media_url')->nullable()->after('header_text');

            // Footer field for question_button nodes
            $table->string('footer_text', 60)->nullable()->after('options');

            // Template node fields
            $table->foreignUlid('template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('template_parameters')->nullable();

            $table->json('options')->nullable();
            $table->string('variable_name')->nullable();
            $table->boolean('use_fallback')->default(false);
            $table->ulid('fallback_node_id')->nullable();

            // Assign chat node
            $table->enum('assign_type', AssignType::values())->nullable();
            $table->foreignUlid('assign_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('assign_to_bot_id')->nullable()->constrained('bots')->nullOnDelete();

            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();

            // Condition node fields
            $table->enum('condition_operator', ComparisonOperator::values())->nullable();
            $table->string('condition_value')->nullable(); // literal value or variable name
            $table->foreignUlid('condition_value_variable_id')->nullable()->constrained('bot_variables')->nullOnDelete();
            $table->json('conditions')->nullable();

            $table->timestamps();

            $table->index(['bot_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_nodes');
    }
};
