<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BroadcastController;
use App\Http\Controllers\Api\BusinessesController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactFieldController;
use App\Http\Controllers\Api\ContactImportController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\IndustryController;
use App\Http\Controllers\Api\KnownPlaceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TemplateCategoryController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TemplateHeaderTypeController;
use App\Http\Controllers\Api\TemplateLanguageController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TimezoneController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WabaController;
use App\Http\Middleware\EnsureWabaId;
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

Route::prefix('meta/callback')->group(function () {
    Route::get('/', [MetaController::class, 'handshake']);
    Route::post('/post', [MetaController::class, 'callback']);
});

Route::group(['middleware' => [
    InitializeTenancyByRequestData::class,
    'auth:api',
]], function () {

    Route::get('/conversations', [ConversationController::class, 'index'])->middleware([EnsureWabaId::class]);
    Route::get('/messages', [MessageController::class, 'index']);

    // DEBGU ENDPOINTs
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::post('/messages', [MessageController::class, 'store']);

    Route::post('/send-message', [ConversationController::class, 'sendMessage']);

    Route::prefix('meta')->group(function () {
        Route::get('app-id', [MetaController::class, 'getAppId']);
    });

    Route::post('tenant/meta-access', [TenantController::class, 'metaAccess']);
    Route::post('tenant/complete-profile', [TenantController::class, 'completeProfile']);

    Route::prefix('businesses')->group(function () {
        Route::get('/', [BusinessesController::class, 'index']);
    });

    Route::get('permissions', [PermissionController::class, 'index']);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('teams', TeamController::class);

    Route::prefix('templates')
        ->group(function () {
            Route::get('/languages', [TemplateLanguageController::class, 'index']);
            Route::get('/categories', [TemplateCategoryController::class, 'index']);
            Route::get('/header-types', [TemplateHeaderTypeController::class, 'index']);
            Route::get('/{template}/active-broadcasts', [TemplateController::class, 'activeBroadcasts'])->middleware([EnsureWabaId::class]);
        });
    Route::apiResource('templates', TemplateController::class)
        ->middleware([EnsureWabaId::class]);

    Route::get('wabas', [WabaController::class, 'index']);

    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('users/{id}/restore', [UserController::class, 'restore']);
    Route::apiResource('users', UserController::class);

    Route::get('contacts/fields/types', [ContactFieldController::class, 'types']);
    Route::put('contacts/fields/{contactField}/change-status', [ContactFieldController::class, 'changeStatus']);
    Route::put('contacts/fields/{contactField}/change-mandatory', [ContactFieldController::class, 'changeMandatory']);
    Route::apiResource('contacts/fields', ContactFieldController::class)->only(['index', 'store', 'destroy', 'update']);

    Route::get('contacts/import', [ContactImportController::class, 'index']);
    Route::get('contacts/import/{history}', [ContactImportController::class, 'show']);
    Route::post('contacts/import', [ContactImportController::class, 'import']);
    Route::post('contacts/import-history', [ContactImportController::class, 'import-history']);
    Route::apiResource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::apiResource('groups', GroupController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::apiResource('broadcasts', BroadcastController::class)->only(['index', 'store']);
});
