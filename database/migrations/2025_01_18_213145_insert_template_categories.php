<?php

use App\Models\TemplateCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templateCategories = collect([
            ['name' => 'Marketing'],
        ])->transform(function ($templateCategory) {
            return [
                'id' => Str::ulid(),
                'name' => $templateCategory['name'],
            ];
        });

        TemplateCategory::insert($templateCategories->toArray());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
