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
        Schema::create('tenant_otps', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('expire_at');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6);
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
