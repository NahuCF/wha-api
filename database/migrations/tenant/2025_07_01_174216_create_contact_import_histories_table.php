<?php

use App\Enums\ContactImportStatus;
use App\Enums\ContactImportType;
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
        Schema::create('contact_import_histories', function (Blueprint $table) {
            $table->ulid('id');
            $table->foreignUlid('user_id')->constrained();
            $table->enum('import_type', ContactImportType::values());
            $table->integer('added_contacts_count')->default(0);
            $table->integer('error_contacts_count')->default(0);
            $table->string('file_path');
            $table->enum('status', ContactImportStatus::values())->default(ContactImportStatus::PENDING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_import_histories');
    }
};
