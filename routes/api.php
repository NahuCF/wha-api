<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactFieldController;
use App\Http\Controllers\Api\ContactImportController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\IndustryController;
use App\Http\Controllers\Api\KnownPlaceController;
use App\Http\Controllers\Api\TemplateCategoryController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TemplateHeaderTypeController;
use App\Http\Controllers\Api\TemplateLanguageController;
use App\Http\Controllers\Api\TimezoneController;
use App\Http\Controllers\Api\UserController;
use App\Models\Callback;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

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

Route::get('waba/callback', function () {
    $request = request();

    $mode = $request->query('hub_mode');
    $token = $request->query('hub_verify_token');
    $challenge = $request->query('hub_challenge');

    if ($mode === 'subscribe' && $token === 'test') {
        // Tokens match, return the challenge
        return response($challenge, 200);
    }

    // Tokens do not match
    return response('Forbidden', 403);
});

Route::post('waba/callback', function () {
    Callback::query()
        ->create([
            'data' => json_encode(request()->all()),
        ]);

    return response('', 200);
});

Route::group(['middleware' => [
    InitializeTenancyByRequestData::class,
    'auth:api',
]], function () {
    Route::prefix('templates')->group(function () {
        Route::get('/languages', [TemplateLanguageController::class, 'index']);
        Route::get('/categories', [TemplateCategoryController::class, 'index']);
        Route::get('/header-types', [TemplateHeaderTypeController::class, 'index']);
    });
    Route::apiResource('templates', TemplateController::class)->only(['index', 'store']);

    Route::apiResource('users', UserController::class)->only(['index']);

    Route::get('contacts/fields/types', [ContactFieldController::class, 'types']);
    Route::put('contacts/fields/{contactField}/change-status', [ContactFieldController::class, 'changeStatus']);
    Route::put('contacts/fields/{contactField}/change-mandatory', [ContactFieldController::class, 'changeMandatory']);
    Route::apiResource('contacts/fields', ContactFieldController::class)->only(['index', 'store', 'destroy', 'update']);

    Route::post('contacts/import', [ContactImportController::class, 'import']);
    Route::post('contacts/import-history', [ContactImportController::class, 'import-history']);
    Route::apiResource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
});
