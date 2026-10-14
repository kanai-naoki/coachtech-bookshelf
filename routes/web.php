<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\BookSearchController;
use Illuminate\Support\Facades\Route;

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
    // Isbn検索（Google Books API）
    Route::get('/books/isbn/{isbn}', [BookSearchController::class, 'show'])->name('books.isbn.search');
    // マイ読書レポート
    Route::get('/reports', function () {
        return 'マイ読書レポート画面 (準備中)';
    })->name('reports.index');

    // 読書計画（一覧、作成、編集、完了など）
    Route::get('/reading-plans', function () {
        return '読書計画一覧画面 (準備中)';
    })->name('reading-plans.index');
    Route::get('/reading-plans/create', function () {
        return '読書計画作成画面 (準備中)';
    })->name('reading-plans.create');
    Route::post('/reading-plans', function () {
        // 登録処理
    })->name('reading-plans.store');
    Route::get('/reading-plans/{plan}/edit', function ($plan) {
        return "読書計画編集画面 ID: {$plan} (準備中)";
    })->name('reading-plans.edit');
    Route::put('/reading-plans/{plan}', function ($plan) {
        // 更新処理
    })->name('reading-plans.update');
    Route::post('/reading-plans/{plan}/complete', function ($plan) {
        // 読了する処理
    })->name('reading-plans.complete');
    Route::delete('/reading-plans/{plan}', function ($plan) {
        // 削除処理
    })->name('reading-plans.destroy');

    // 通知一覧
    Route::get('/notifications', function () {
        return '通知一覧画面 (準備中)';
    })->name('notifications.index');
    Route::post('/notifications/{id}/read', function ($id) {
        // 既読にする処理
    })->name('notifications.read');
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
