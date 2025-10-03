<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No fields needed for mark_as_solved node
        // The node simply marks the conversation as solved
    }

    public function down(): void
    {
        // Nothing to drop
    }
};
