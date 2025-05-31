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
        Schema::create('template_buttons', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('template_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['QUICK_REPLY', 'PHONE_NUMBER', 'STATIC_URL', 'DYNAMIC_URL']);
            $table->string('text');
            $table->string('url')->nullable();
            $table->string('phone_prefix')->nullable();
            $table->string('phone_number')->nullable();
            $table->unsignedTinyInteger('index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_buttons');
    }
};
