<?php

use App\Enums\FlowConditionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

            Schema::create('bot_edges', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('bot_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('bot_flow_id')->nullable()->constrained('bot_flows')->cascadeOnDelete();

                $table->string('edge_id')->nullable(); // VueFlow edge ID
                $table->string('source_node_id');
                $table->string('target_node_id');

                $table->enum('condition_type', FlowConditionType::values())->default(FlowConditionType::ALWAYS->value);
                $table->string('condition_value')->nullable();

                $table->timestamps();

                $table->index(['bot_id', 'source_node_id']);
                $table->index(['bot_id', 'target_node_id']);
                $table->index('bot_flow_id');
                $table->unique(['bot_flow_id', 'edge_id']);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_edges');
    }
};
