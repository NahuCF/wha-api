<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\IndustryController;
use App\Http\Controllers\Api\KnownPlaceController;
use App\Http\Controllers\Api\TimezoneController;
use App\Models\Callback;
use Illuminate\Support\Facades\Route;

Route::get('/up', fn () => response('', 200));

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/send-verify-account', [AuthController::class, 'sendVerifyAccount']);
Route::post('/verify-account', [AuthController::class, 'verifyAccount']);
Route::put('/store-basic-information/{tenant}', [AuthController::class, 'storeBasicInformation']);
Route::post('/resend-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::get('/tenant-user', [AuthController::class, 'tenantUser']);

Route::get('/countries', [CountryController::class, 'index']);
Route::get('/currencies', [CurrencyController::class, 'index']);
Route::get('/timezones', [TimezoneController::class, 'index']);
Route::get('/known-places', [KnownPlaceController::class, 'index']);
Route::get('/industries', [IndustryController::class, 'index']);

Route::any('waba/callback', function () {
    Callback::query()
        ->create([
            'data' => json_encode(request()->all()),
        ]);
});
