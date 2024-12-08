<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\IndustryController;
use App\Http\Controllers\Api\KnownPlaceController;
use App\Http\Controllers\Api\TimezoneController;
use Illuminate\Support\Facades\Route;

Route::get('/up', fn () => response('', 200));

Route::post('/register', [AuthController::class, 'register']);
Route::put('/store-basic-information/{tenant}', [AuthController::class, 'storeBasicInformation']);
Route::post('/resend-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

Route::get('/countries', [CountryController::class, 'index']);
Route::get('/currencies', [CurrencyController::class, 'index']);
Route::get('/timezones', [TimezoneController::class, 'index']);
Route::get('/known-places', [KnownPlaceController::class, 'index']);
Route::get('/industries', [IndustryController::class, 'index']);
