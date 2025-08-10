<?php

use App\Enums\ContactFieldType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignUlid('user_id')->nullable()->constrained();
            $table->string('name');
            $table->string('internal_name');
            $table->enum('type', ContactFieldType::values());
            $table->json('options')->nullable();
            $table->boolean('is_primary_field')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_mandatory')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_fields');
    }
};
