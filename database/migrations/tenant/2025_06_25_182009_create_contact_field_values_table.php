<?php

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
        Schema::create('contact_field_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('contact_id')->constrained();
            $table->foreignUlid('contact_field_id')->constrained();
            $table->jsonb('value');
            $table->timestamps();

            $table->unique(['contact_id', 'contact_field_id']);
        });

        DB::statement('CREATE INDEX idx_contact_field_values_value_jsonb ON contact_field_values USING GIN (value jsonb_path_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_contact_field_values_value_jsonb');
        Schema::dropIfExists('contact_field_values');
    }
};
