<?php

namespace App\Jobs;

use App\Enums\ContactFieldType;
use App\Enums\ContactImportStatus;
use App\Models\ContactField;
use App\Models\ContactImportHistory;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

class ImportContactsFromUpload implements ShouldQueue
{
    public $queue = 'imports';  // Low priority import queue

    public $timeout = 900;  // 15 minutes for large imports

    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $filePath,
        private readonly array $mappings,
        private readonly string $historyId
    ) {}

    public function handle(): void
    {
        try {
            Tenancy::initialize($this->tenantId);

            $history = ContactImportHistory::find($this->historyId);
            $history->update([
                'status' => ContactImportStatus::PROCESSING,
            ]);

            // Preload field IDs by name for quick lookup
            $mappingByName = collect($this->mappings)->pluck('id', 'name');

            $contactFieldsById = ContactField::withoutGlobalScopes()
                ->where(function ($query) {
                    $query->whereNull('tenant_id')
                        ->orWhere('tenant_id', tenant('id'));
                })
                ->get()
                ->keyBy('id');

            // Temporary file
            $filename = 'temp_'.uniqid().'.xlsx';
            Storage::put(
                $filename,
                Storage::disk('s3')->get($this->filePath)
            );
            $tempPath = Storage::path($filename);

            SimpleExcelReader::create($tempPath)
                ->getRows()
                ->chunk(self::CHUNK_SIZE)
                ->each(function ($rows) use ($mappingByName, $contactFieldsById, $history) {
                    $contactsData = [];
                    $fieldValues = [];

                    foreach ($rows as $row) {
                        // prepare new contact record
                        $contactsData[] = [
                            'id' => \Illuminate\Support\Str::ulid(),
                            'tenant_id' => tenant('id'),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $newContactKey = array_key_last($contactsData);
                        $contactId = $contactsData[$newContactKey]['id'];

                        // collect field values for this contact
                        foreach ($row as $colName => $value) {
                            if (! isset($mappingByName[$colName])) {
                                continue;
                            }

                            $fieldId = $mappingByName[$colName];
                            $field = $contactFieldsById[$fieldId];

                            $isFieldArray = ContactFieldType::arrayTypeValues()->contains($field->type);

                            $parsedValue = $value;

                            if ($isFieldArray) {
                                if (str_contains($value, ',')) {
                                    $parsedValue = explode(',', $value);
                                } else {
                                    $parsedValue = [$value];
                                }
                            }

                            if ($this->validateValue($fieldId, $value)) {
                                $fieldValues[] = [
                                    'id' => \Illuminate\Support\Str::ulid(),
                                    'contact_id' => $contactId,
                                    'contact_field_id' => $fieldId,
                                    'tenant_id' => tenant('id'),
                                    'value' => json_encode($parsedValue),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                        }
                    }

                    // Bulk insert contacts and field values within a transaction
                    DB::transaction(function () use ($contactsData, $fieldValues, $history) {
                        DB::table('contacts')->insert($contactsData);

                        $history->update([
                            'added_contacts_count' => $history->added_contacts_count + count($contactsData),
                        ]);

                        if (! empty($fieldValues)) {
                            DB::table('contact_field_values')->insert($fieldValues);
                        }
                    });
                });

            $history->update([
                'status' => ContactImportStatus::COMPLETED,
            ]);

            @unlink($tempPath);

        } catch (Throwable $e) {
            Log::error("ImportContactsFromUpload failed for tenant {$this->tenantId}: {$e->getMessage()}", [
                'exception' => $e,
                'filePath' => $this->filePath,
            ]);

            // rethrow to mark job failed
            throw $e;
        }
    }

    private function validateValue(string $fieldId, $value): bool
    {
        // You can preload field types by id if further optimized
        // $fieldType = ContactField::find($fieldId)->type;

        // Example simple validation; adapt per your ContactFieldType
        return true;
    }
}
