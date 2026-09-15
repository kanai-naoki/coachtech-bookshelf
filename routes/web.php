<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
// １．ログインユーザーのみ利用できる機能
Route::middleware(['auth'])->group(function () {
    // ジャンル一覧・詳細・編集（追加・削除）
    Route::resource('genres', GenreController::class);
    // 書籍登録・編集・削除
    Route::resource('books', BookController::class)->except([
        'index',
        'show',
    ]);
    // レビュー投稿・編集・削除
    Route::resource('books.reviews', ReviewController::class)
        ->shallow()
        ->only(['store', 'edit', 'update', 'destroy'])->names([
                'store' => 'reviews.store',
            ]);
    // レビューいいね
    Route::post('/reviews/{review}/like', ReviewLikeController::class)->name('reviews.like');
    // お気に入り一覧画面
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    // お気に入り（追加・解除）
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
});

// ２. 誰でもアクセス可能なルート（ゲスト可）
// 書籍一覧
Route::get('/', [BookController::class, 'index'])->name('books.index');
// 書籍一覧（/booksでも表示される）・書籍詳細
Route::resource('books', BookController::class)->only([
    'index',
    'show',
]);
// ランキング
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

