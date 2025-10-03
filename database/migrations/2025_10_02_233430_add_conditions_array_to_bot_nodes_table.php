<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            // Array of conditions for AND logic
            // Each condition: {variable_id, operator, value, value_variable_id}
            $table->json('conditions')->nullable()->after('condition_value_variable_id');
        });
    }

    public function down(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            $table->dropColumn('conditions');
        });
    }
};