<?php

use App\Enums\PhoneNumberStatus;
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
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('waba_id')->constrained()->onDelete('cascade');
            $table->foreignUlid('tenant_id');

            $table->string('meta_id')->unique();
            $table->string('display_phone_number');
            $table->string('verified_name')->nullable();
            $table->string('quality_rating')->nullable();
            $table->string('code_verification_status')->default('NOT_VERIFIED');
            $table->string('pin', 6)->nullable();
            $table->boolean('is_registered')->default(false);
            $table->string('about', 139)->nullable();
            $table->string('address', 256)->nullable();
            $table->text('description')->nullable();
            $table->string('email', 128)->nullable();
            $table->string('vertical', 50)->nullable();
            $table->json('websites')->nullable();
            $table->string('profile_picture_path')->nullable();
            $table->string('profile_picture_handle')->nullable();
            $table->enum('status', PhoneNumberStatus::values());

            $table->timestamp('profile_updated_at')->nullable();
            $table->timestamps();

            $table->index('waba_id');
            $table->index('display_phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
