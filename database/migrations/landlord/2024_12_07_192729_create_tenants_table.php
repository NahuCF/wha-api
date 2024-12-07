<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
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
            $table->boolean('verified_email')->default(false);
            $table->boolean('account_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
