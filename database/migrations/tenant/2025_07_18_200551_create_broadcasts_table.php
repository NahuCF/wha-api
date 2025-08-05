<?php

use App\Enums\BroadcastStatus;
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
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->ulid('id');
            $table->string('name');
            $table->timestamp('send_at');
            $table->boolean('follow_whatsapp_business_policy')->default(false);
            $table->foreignUlid('user_id')->constrained();
            $table->foreignUlid('template_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('group_id')->constrained();
            $table->enum('status', BroadcastStatus::values())->default(BroadcastStatus::WAITING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
