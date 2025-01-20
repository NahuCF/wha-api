<?php

use App\Models\HeaderComponentType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $componentTypes = [
            ['name' => 'Text', 'code' => 'TEXT'],
            ['name' => 'Image', 'code' => 'IMAGE'],
            ['name' => 'Video', 'code' => 'VIDEO'],
            ['name' => 'Document', 'code' => 'DOCUMENT'],
        ];

        HeaderComponentType::insert($componentTypes);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
