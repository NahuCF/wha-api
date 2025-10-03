<?php

use App\Enums\MessageDirection;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
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
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('template_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignUlid('broadcast_id')->nullable()->constrained();

            $table->ulid('reply_to_message_id')->nullable()->index();
            $table->string('meta_id')->nullable()->unique()->index();
            $table->enum('direction', MessageDirection::values());
            $table->enum('type', MessageType::values());
            $table->enum('status', MessageStatus::values())->nullable();
            $table->enum('source', MessageSource::values())->default(MessageSource::WHATSAPP->value);
            $table->text('content')->nullable();
            $table->text('display_content')->nullable();
            $table->json('media')->nullable();
            $table->json('interactive_data')->nullable();
            $table->json('location_data')->nullable();
            $table->json('contacts_data')->nullable();
            $table->json('variables')->nullable();
            $table->json('mentions')->nullable();
            $table->json('errors')->nullable();
            $table->string('to_phone')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('deleted_at');
            $table->index(['direction', 'status']);
            $table->index('type');
            $table->index('source');
            $table->index('broadcast_id');
            $table->index('created_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
