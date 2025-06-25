<?php

use App\Models\ContactField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('internal_name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_primary_field')->default(false);
            $table->enum('type', ContactField::types());
            $table->jsonb('options')->nullable(); // in case is array
            $table->foreignUlid('user_id')->nullable()->constrained();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX contact_fields_options_gin ON contact_fields USING GIN (options);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_fields');
    }
};
