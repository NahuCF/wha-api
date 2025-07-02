<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactImportType;
use App\Http\Controllers\Controller;
use App\Jobs\ImportContactsFromUpload;
use App\Models\ContactImportHistory;
use App\Services\JobDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactImportController extends Controller
{
    public function import(Request $request)
    {
        $input = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'import_type' => ['required', 'string', Rule::in(ContactImportType::values())],

            'mappings' => ['required', 'array'],
            'mappings.*.name' => ['required', 'string'],
            'mappings.*.id' => ['required', 'string', 'ulid', Rule::exists('contact_fields', 'id')],
        ]);

        $user = Auth::user();

        $file = $request->file('file');
        $importType = data_get($input, 'import_type');
        $mappings = collect(data_get($input, 'mappings'));

        $path = 'contact-imports/'.tenant()->id.'/'.$file->getClientOriginalName();

        $s3Path = Storage::disk('s3')->putFileAs('', $file, $path);

        ContactImportHistory::create([
            'id' => Str::ulid(),
            'user_id' => $user->id,
            'import_type' => $importType,
            'file_path' => $s3Path,
        ]);

        JobDispatcherService::dispatch(
            new ImportContactsFromUpload(
                tenantId: tenant()->id,
                filePath: $s3Path,
                mappings: $mappings->toArray()
            ),
            'heavy'
        );

        return response()->json([
            'message' => 'File uploaded successfully',
            'path' => $s3Path,
            'url' => Storage::disk('s3')->url($s3Path),
        ]);
    }
}
