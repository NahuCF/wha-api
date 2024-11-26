<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/up', fn () => response('', 200));

Route::post('/register', [AuthController::class, 'register']);
