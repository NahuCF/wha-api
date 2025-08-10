<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('company_name');
            $table->foreignId('country_id')->nullable()->constrained();
            $table->foreignId('currency_id')->nullable()->constrained();
            $table->foreignId('timezone_id')->nullable()->constrained();
            $table->integer('employees_amount')->nullable();
            $table->foreignId('known_place_id')->nullable()->constrained();
            $table->boolean('verified_email')->default(false);
            $table->boolean('filled_basic_information')->default(false);
            $table->boolean('verified_whatsapp')->default(false);
            $table->string('short_lived_access_token')->nullable();
            $table->timestamp('short_lived_access_token_expires_at')->nullable();
            $table->string('long_lived_access_token')->nullable();
            $table->timestamp('long_lived_access_token_expires_at')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
