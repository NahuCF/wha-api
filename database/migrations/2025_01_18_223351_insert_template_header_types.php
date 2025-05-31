<?php

use App\Models\TemplateHeaderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $componentTypes = collect([
            ['name' => 'Text', 'code' => 'TEXT'],
            ['name' => 'Image', 'code' => 'IMAGE'],
            ['name' => 'Video', 'code' => 'VIDEO'],
            ['name' => 'Document', 'code' => 'DOCUMENT'],
        ])->transform(function ($componentType) {
            return [
                'id' => Str::ulid(),
                'name' => $componentType['name'],
                'code' => $componentType['code'],
            ];
        });

        TemplateHeaderType::insert($componentTypes->toArray());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
