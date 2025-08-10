<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactImportType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactImportHistoryResource;
use App\Jobs\ImportContactsFromUpload;
use App\Models\ContactImportHistory;
use App\Services\JobDispatcherService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactImportController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'rows_per_page' => ['sometimes'],
        ]);

        $rowsPerPage = data_get($input, 'rows_per_page', 10);

        $histories = ContactImportHistory::query()
            ->with('user')
            ->orderBy('id', 'desc')
            ->paginate($rowsPerPage);

        return ContactImportHistoryResource::collection($histories);
    }

    public function show(Request $request, ContactImportHistory $history)
    {
        return new ContactImportHistoryResource($history);
    }

    public function import(Request $request)
    {
        $input = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'name' => ['required', 'string'],
            'import_type' => ['required', 'string', Rule::in(ContactImportType::values())],
            'mappings' => ['required', 'array'],
            'mappings.*.name' => ['required', 'string'],
            'mappings.*.id' => ['required', 'string', 'ulid', Rule::exists('contact_fields', 'id')],
        ]);

        $user = Auth::user();

        $file = $request->file('file');
        $importType = data_get($input, 'import_type');
        $mappings = collect(data_get($input, 'mappings'));
        $name = data_get($input, 'name');

        $nameExist = ContactImportHistory::query()
            ->where('name', $name)
            ->exists();

        if ($nameExist) {
            throw ValidationException::withMessages(['name' => 'Import name already exists']);
        }

        $path = 'contact-imports/'.tenant()->id.'/'.$file->getClientOriginalName();

        $s3Path = Storage::disk('s3')->putFileAs('', $file, $path);
        if (! $s3Path) {
            throw new Exception('File not uploaded');
        }

        $history = ContactImportHistory::create([
            'id' => Str::ulid(),
            'user_id' => $user->id,
            'tenant_id' => tenant('id'),
            'name' => $name,
            'import_type' => $importType,
            'file_path' => $s3Path,
        ]);

        JobDispatcherService::dispatch(
            new ImportContactsFromUpload(
                tenantId: tenant()->id,
                filePath: $s3Path,
                mappings: $mappings->toArray(),
                historyId: $history->id
            ),
            'heavy'
        );

        return response()->noContent();
    }
}
