<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController;
use \App\Http\Controllers\LinkController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group([
    'prefix' => config('app.api-version')
], function () {
    Route::group(['prefix' => 'passdropit'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
            Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])
                ->name('reset-password');
        });
    });

    Route::group(['prefix' => 'notions11'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
            Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])
                ->name('reset-password');
        });
    });
});

Route::group([
    'prefix' => config('app.api-version'),
    'middleware' => ['auth:sanctum']
], function () {
    Route::group(['prefix' => 'passdropit'], function () {
        Route::prefix('auth')->group(function () {
            Route::post('/reset-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
        Route::prefix('link')->group(function () {
            Route::post('/check-google-link', [LinkController::class, 'checkGoogleLink']);
            Route::post('/save-link', [LinkController::class, 'saveLink']);
        });

    });

    Route::group(['prefix' => 'notions11'], function () {
        Route::prefix('auth')->group(function () {
            Route::post('/reset-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::prefix('link')->group(function () {
            Route::post('/save-link', [LinkController::class, 'saveLink']);
        });
    });
});

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});
