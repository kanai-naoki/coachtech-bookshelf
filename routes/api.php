<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // 【公開API】一覧・詳細取得（未認証でもアクセス可能）
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{book}', [BookController::class, 'show']);

    // 認証（ログイン）
    Route::post('/login', [AuthController::class, 'login']);
    // 【Sanctum保護API】書き込み系エンドポイント
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{book}', [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);
        // ログアウト
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
