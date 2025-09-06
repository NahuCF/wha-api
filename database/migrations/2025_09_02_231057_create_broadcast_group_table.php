<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('broadcast_group', function (Blueprint $table) {
            $table->foreignUlid('broadcast_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('group_id')->constrained()->onDelete('cascade');

            $table->primary(['broadcast_id', 'group_id']);
            $table->index('broadcast_id');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_group');
    }
};
