<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            $table->dropForeign(['condition_variable_id']);
            $table->dropForeign(['condition_value_variable_id']);
            $table->dropColumn([
                'condition_variable_id',
                'condition_operator',
                'condition_value',
                'condition_value_variable_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            $table->foreignUlid('condition_variable_id')->nullable()->constrained('bot_variables')->nullOnDelete();
            $table->string('condition_operator')->nullable();
            $table->string('condition_value')->nullable();
            $table->foreignUlid('condition_value_variable_id')->nullable()->constrained('bot_variables')->nullOnDelete();
        });
    }
};