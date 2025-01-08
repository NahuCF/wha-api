<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('business_name');
            $table->string('website');
            $table->string('email')->unique();
            $table->string('database')->unique();
            $table->foreignId('country_id')->nullable()->constrained();
            $table->foreignId('currency_id')->nullable()->constrained();
            $table->foreignId('timezone_id')->nullable()->constrained();
            $table->integer('employees_amount')->nullable();
            $table->foreignId('known_place_id')->nullable()->constrained();
            $table->boolean('verified_email')->default(false);
            $table->boolean('filled_basic_information')->default(false);
            $table->boolean('verified_whatsapp')->default(false);
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
