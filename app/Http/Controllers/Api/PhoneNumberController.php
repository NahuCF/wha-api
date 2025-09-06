<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneNumberResource;
use App\Models\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhoneNumberController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'waba_id' => ['required', 'ulid', Rule::exists('wabas', 'id')],
        ]);

        $wabaId = data_get($input, 'waba_id');

        $numbers = PhoneNumber::query()
            ->where('waba_id', $wabaId)
            ->orderBy('id', 'desc')
            ->get();

        return PhoneNumberResource::collection($numbers);

    }
}
