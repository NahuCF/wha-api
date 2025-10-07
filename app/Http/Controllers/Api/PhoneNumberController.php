<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AppEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneNumberResource;
use App\Models\PhoneNumber;
use App\Services\MetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function showProfile(PhoneNumber $phoneNumber)
    {
        return PhoneNumberResource::make($phoneNumber);
    }

    public function updateProfile(Request $request, PhoneNumber $phoneNumber)
    {
        $input = $request->validate([
            'about' => ['nullable', 'string', 'min:1', 'max:139'],
            'address' => ['nullable', 'string', 'max:256'],
            'description' => ['nullable', 'string', 'max:512'],
            'email' => ['nullable', 'email', 'max:128'],
            'vertical' => ['nullable', Rule::in(PhoneNumber::ALLOWED_VERTICALS)],
            'websites' => ['nullable', 'array', 'max:2'],
            'websites.*' => ['required', 'url', 'max:256'],
        ]);

        $phoneNumber->update([
            'about' => data_get($input, 'about'),
            'address' => data_get($input, 'address'),
            'description' => data_get($input, 'description'),
            'email' => data_get($input, 'email'),
            'vertical' => data_get($input, 'vertical'),
            'websites' => data_get($input, 'websites'),
        ]);

        if (AppEnvironment::isProduction()) {
            (new MetaService)->updatePhoneNumberProfile($phoneNumber);
        }

        return PhoneNumberResource::make($phoneNumber->fresh());
    }

    public function uploadProfilePicture(Request $request, PhoneNumber $phoneNumber)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $image = $request->file('image');

        $filename = Str::ulid().'.'.$image->getClientOriginalExtension();
        $path = "phone-numbers/{$phoneNumber->id}/profile-pictures/{$filename}";

        $storedPath = Storage::disk('s3')->put($path, file_get_contents($image));

        $phoneNumber->update([
            'profile_picture_path' => $storedPath,
            'profile_updated_at' => now(),
        ]);

        if (AppEnvironment::isProduction()) {
            $handle = (new MetaService)->uploadPhoneNumberProfilePicture($phoneNumber, $image);

            $phoneNumber->update([
                'profile_picture_handle' => $handle,
            ]);
        }

        return PhoneNumberResource::make($phoneNumber->fresh());

    }
}
