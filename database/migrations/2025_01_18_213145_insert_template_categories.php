<?php

use App\Models\TemplateCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templateCategories = [
            ['name' => 'Marketing'],
        ];

        TemplateCategory::insert($templateCategories);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
