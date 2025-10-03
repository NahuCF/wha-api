<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            // Header fields for question_button nodes
            $table->enum('header_type', ['text', 'image', 'video', 'document'])->nullable()->after('content');
            $table->text('header_text')->nullable()->after('header_type');
            $table->string('header_media_url')->nullable()->after('header_text');
            
            // Footer field for question_button nodes
            $table->string('footer_text', 60)->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('bot_nodes', function (Blueprint $table) {
            $table->dropColumn([
                'header_type',
                'header_text',
                'header_media_url',
                'footer_text'
            ]);
        });
    }
};