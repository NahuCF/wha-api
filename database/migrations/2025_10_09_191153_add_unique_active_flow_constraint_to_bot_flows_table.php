<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX unique_active_flow_per_bot 
            ON bot_flows (bot_id, status) 
            WHERE status = 'active'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_active_flow_per_bot');
    }
};
