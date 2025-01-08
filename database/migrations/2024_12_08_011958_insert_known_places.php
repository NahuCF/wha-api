<?php

use App\Models\KnownPlace;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $data = [
            ['name' => 'Google'],
            ['name' => 'Instagram'],
            ['name' => 'Facebook'],
            ['name' => 'Twitter'],
            ['name' => 'Linkedin'],
            ['name' => 'A friend'],
            ['name' => 'Another'],
        ];

        KnownPlace::insert($data);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
