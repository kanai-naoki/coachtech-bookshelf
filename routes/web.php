<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookSearchController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReadingReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
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
    // ジャンル一覧・詳細・CRUD
    Route::resource('genres', GenreController::class);
    // 書籍CRUD
    Route::resource('books', BookController::class)->except([
        'index',
        'show',
    ]);
    // レビューCRUD
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
    Route::get('/books/isbn/{isbn?}', [BookSearchController::class, 'show'])->name('books.isbn.search');
    // 読書計画一覧・CRUD
    Route::resource('reading-plans', ReadingPlanController::class)->except(['show']);
    // 読書計画 「読了」処理 (POST /reading-plans/{reading_plan}/complete)
    Route::post('/reading-plans/{reading_plan}/complete', [ReadingPlanController::class, 'complete'])->name('reading-plans.complete');
    // 通知一覧
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    // 通知既読化
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    // マイ読書レポート
    Route::get('/reports', [ReadingReportController::class, 'index'])->name('reports.index');
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
