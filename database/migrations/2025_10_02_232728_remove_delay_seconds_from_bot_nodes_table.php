<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            $table->dropColumn('delay_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            $table->integer('delay_seconds')->nullable()->after('assign_to_bot_id');
        });
    }
};