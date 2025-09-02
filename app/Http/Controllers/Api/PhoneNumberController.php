<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneNumberResource;
use App\Models\PhoneNumber;

class PhoneNumberController extends Controller
{
    public function index()
    {
        $numbers = PhoneNumber::query()
            ->orderBy('id', 'desc')
            ->get();

        return PhoneNumberResource::collection($numbers);

    }
}
