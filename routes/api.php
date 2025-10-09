<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\BotFlowController;
use App\Http\Controllers\Api\BotVariableController;
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
use App\Http\Controllers\Api\PhoneNumberController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TemplateCategoryController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TemplateHeaderTypeController;
use App\Http\Controllers\Api\TemplateLanguageController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantSettingsController;
use App\Http\Controllers\Api\TimezoneController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WabaController;
use App\Http\Middleware\EnsureWabaId;
use App\Models\Bot;
use Illuminate\Support\Facades\Broadcast;
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
    Broadcast::routes();

    Route::group(['middleware' => [EnsureWabaId::class]], function () {
        Route::post('/conversations/stats', [ConversationController::class, 'stats']);
        Route::put('/conversations/{conversation}/change-solved', [ConversationController::class, 'changeSolved']);
        Route::put('/conversations/{conversation}/change-owner', [ConversationController::class, 'changeOwner']);
        Route::post('/conversations/{conversation}/pin', [ConversationController::class, 'pin']);
        Route::delete('/conversations/{conversation}/pin', [ConversationController::class, 'unpin']);
        Route::get('/conversations/{conversation}/activities', [ConversationController::class, 'activities']);
        Route::apiResource('/conversations', ConversationController::class)->only(['index', 'store', 'show']);
        Route::post('/message-contact', [MessageController::class, 'storeTest']);
        Route::post('/messages/{message}/test-deleted', [MessageController::class, 'testDeletedEvent']);
        Route::apiResource('/messages', MessageController::class)->only(['index', 'store']);
    });

    Route::prefix('meta')->group(function () {
        Route::get('app-id', [MetaController::class, 'getAppId']);
    });

    Route::post('tenant/meta-access', [TenantController::class, 'metaAccess']);
    Route::post('tenant/store-default-waba', [TenantController::class, 'storeDefaultWaba']);
    Route::post('tenant/select-number', [TenantController::class, 'selectNumber']);
    Route::post('tenant/verify-number-code', [TenantController::class, 'verifyNumberCode']);
    Route::delete('tenant/disconnect-phone', [TenantController::class, 'disconnectPhoneNumber']);

    Route::get('tenant/settings', [TenantSettingsController::class, 'show']);
    Route::put('tenant/settings', [TenantSettingsController::class, 'update']);

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

    Route::get('phone-numbers', [PhoneNumberController::class, 'index']);
    Route::get('phone-numbers/verticals', [PhoneNumberController::class, 'verticals']);

    Route::prefix('phone-numbers/{phoneNumber}')->group(function () {
        Route::get('profile', [PhoneNumberController::class, 'showProfile']);
        Route::put('profile', [PhoneNumberController::class, 'updateProfile']);
        Route::post('profile/picture', [PhoneNumberController::class, 'uploadProfilePicture']);
    });

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

    Route::group(['middleware' => [EnsureWabaId::class]], function () {
        Route::get('broadcasts/overview', [BroadcastController::class, 'overview']);
        Route::post('broadcasts/{broadcast}/repeat', [BroadcastController::class, 'repeat']);
        Route::post('broadcasts/{broadcast}/update-status', [BroadcastController::class, 'updateStatus']);
        Route::apiResource('broadcasts', BroadcastController::class)->only(['index', 'store', 'show']);
    });

    Route::prefix('bots')->group(function () {
        Route::get('settings', [BotController::class, 'getSettings']);
        Route::put('settings', [BotController::class, 'updateSettings']);
        Route::put('{bot}/configuration', [BotController::class, 'updateConfiguration']);
        Route::post('{bot}/upload-media', [BotController::class, 'uploadNodeMedia']);
        Route::delete('{bot}/delete-media', [BotController::class, 'deleteNodeMedia']);
        Route::post('{bot}/clone', [BotController::class, 'clone']);
        Route::get('{bot}/active-sessions', [BotController::class, 'checkActiveSessions']);

        Route::get('{bot}/flows', [\App\Http\Controllers\Api\BotFlowController::class, 'index']);
        Route::post('{bot}/flows', [\App\Http\Controllers\Api\BotFlowController::class, 'store']);
        Route::delete('{bot}/flows/{flow}', [BotFlowController::class, 'destroyFlow']);
        Route::post('{bot}/flows/{flow}/activate', [BotFlowController::class, 'activate']);
    });
    Route::apiResource('bots', BotController::class);

    Route::apiResource('bot-variables', BotVariableController::class)->only(['index', 'store', 'update', 'destroy']);
});
