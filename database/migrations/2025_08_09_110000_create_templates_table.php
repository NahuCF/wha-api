<?php

use App\Enums\TemplateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->onDelete('cascade');
            $table->string('meta_id')->nullable();
            $table->foreignUlid('waba_id')->constrained();
            $table->string('name', '512');
            $table->string('language');
            $table->enum('category', ['AUTHENTICATION', 'MARKETING', 'UTILITY']);

            $table->string('body', '1024');
            $table->json('body_example_variables')->nullable();
            $table->string('footer', '60')->nullable();

            $table->json('header')->nullable();
            $table->json('buttons')->nullable();

            $table->enum('status', TemplateStatus::values())->default(TemplateStatus::PENDING->value);
            $table->text('reason')->nullable();

            $table->integer('updated_count_while_approved')->default(0);

            $table->timestamp('meta_updated_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['waba_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
