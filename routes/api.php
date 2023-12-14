<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController;
use \App\Http\Controllers\LinkController;
use \App\Http\Controllers\UserController;
use \App\Http\Controllers\AdminController;
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
//            Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('reset-password');
        });

        Route::prefix('link')->group(function () {
            Route::post('/save-link', [LinkController::class, 'saveLink']);
            Route::post('/get-link-detail', [LinkController::class, 'getLinkDetail']);
        });

        Route::prefix('user')->group(function () {
            Route::post('/commit-pro', [UserController::class, 'commitPro']);
        });
    });

    Route::group(['prefix' => 'notions11'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
//            Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('reset-password');
        });

        Route::prefix('link')->group(function () {
            Route::post('/save-link', [LinkController::class, 'saveLink']);
            Route::post('/get-link-detail', [LinkController::class, 'getLinkDetail']);
        });

        Route::prefix('user')->group(function () {
            Route::post('/commit-pro', [UserController::class, 'commitPro']);
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
            Route::get('/get-list', [LinkController::class, 'getList']);
            Route::post('/update-link', [LinkController::class, 'updateLink']);
            Route::delete('/delete-link/{id}', [LinkController::class, 'deleteLink']);
            Route::post('/analytics', [LinkController::class, 'analytics']);
            Route::post('/buy-link', [LinkController::class, 'buyLink']);
        });

        Route::prefix('user')->group(function () {
            Route::get('/subscription', [UserController::class, 'subscription']);
            Route::post('/upgrade-pro', [UserController::class, 'upgradePro']);
            Route::get('/cancel-pro', [UserController::class, 'cancelPro']);
            Route::post('/upload-logo', [UserController::class, 'uploadLogo']);
            Route::delete('/delete-logo', [UserController::class, 'deleteLogo']);
        });

        Route::prefix('admin')->group(function () {
            Route::post('/update-paypal', [AdminController::class, 'updatePaypal']);
            Route::post('/get-earning-link-list', [AdminController::class, 'getEarningLinkList']);
            Route::get('/get-user-list', [AdminController::class, 'getUserList']);
            Route::get('/export-activity', [AdminController::class, 'exportActivity']);
            Route::post('/link-report', [AdminController::class, 'linkReport']);
            Route::post('/user-analytics', [AdminController::class, 'userAnalytics']);
        });
    });

    Route::group(['prefix' => 'notions11'], function () {
        Route::prefix('auth')->group(function () {
            Route::post('/reset-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::prefix('link')->group(function () {
            Route::get('/get-list', [LinkController::class, 'getList']);
            Route::post('/update-link', [LinkController::class, 'updateLink']);
            Route::delete('/delete-link/{id}', [LinkController::class, 'deleteLink']);
            Route::post('/analytics', [LinkController::class, 'analytics']);
            Route::post('/buy-link', [LinkController::class, 'buyLink']);
        });

        Route::prefix('user')->group(function () {
            Route::get('/subscription', [UserController::class, 'subscription']);
            Route::post('/upgrade-pro', [UserController::class, 'upgradePro']);
            Route::get('/cancel-pro', [UserController::class, 'cancelPro']);
            Route::post('/upload-logo', [UserController::class, 'uploadLogo']);
            Route::delete('/delete-logo', [UserController::class, 'deleteLogo']);
        });

        Route::prefix('admin')->group(function () {
            Route::post('/update-paypal', [AdminController::class, 'updatePaypal']);
            Route::post('/get-earning-link-list', [AdminController::class, 'getEarningLinkList']);
            Route::get('/get-user-list', [AdminController::class, 'getUserList']);
            Route::get('/export-activity', [AdminController::class, 'exportActivity']);
            Route::post('/link-report', [AdminController::class, 'linkReport']);
            Route::post('/user-analytics', [AdminController::class, 'userAnalytics']);
        });
    });
});

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});
