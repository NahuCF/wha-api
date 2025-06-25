<?php

use App\Enums\ContactFieldType;
use App\Models\ContactField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $detaultFields = [
            [
                'name' => 'Name',
                'type' => ContactFieldType::TEXT,
                'is_mandatory' => true,
            ],
            [
                'name' => 'Email',
                'type' => ContactFieldType::MULTI_TEXT,
                'is_mandatory' => false,
            ],
            [
                'name' => 'Phone',
                'type' => ContactFieldType::MULTI_TEXT,
                'is_mandatory' => true,
            ],
            [
                'name' => 'Tags',
                'type' => ContactFieldType::MULTI_TEXT,
                'is_mandatory' => false,
            ],
            [
                'name' => 'Marketing OptIn',
                'type' => ContactFieldType::SWITCH,
                'is_mandatory' => true,
            ],
        ];

        $dataToInsert = collect($detaultFields)->map(function ($field) {
            return [
                'id' => Str::ulid(),
                'name' => $field['name'],
                'internal_name' => str_replace(' ', '', $field['name']),
                'type' => $field['type'],
                'is_mandatory' => $field['is_mandatory'],
                'is_primary_field' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });

        ContactField::insert($dataToInsert->toArray());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
