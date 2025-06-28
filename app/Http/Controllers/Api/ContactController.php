<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Services\ContactService;
use Carbon\Carbon;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request, ContactService $service)
    {
        $service->store($request->validated()['fields']);

        return response()->noContent();
    }

    public function isValidDateString(string $value): bool
    {
        try {
            Carbon::parse($value);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
