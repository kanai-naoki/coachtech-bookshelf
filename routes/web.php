<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
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
    // ジャンル一覧・詳細・編集（追加・削除）画面
    Route::resource('genres', GenreController::class);
    // 書籍登録・編集・削除画面
    Route::resource('books', BookController::class)->except([
        'index',
        'show',
    ]);
    // （仮）
    // お気に入り
    Route::get('/favorites', function () {
        return 'Favorites Index Page (Dummy)';
    })->name('favorites.index');

    Route::post('/books/{book}/favorites', function () {
        return back();
    })->name('favorites.toggle');

    // レビュー
    Route::post('/books/{book}/reviews', function () {
        return back();
    })->name('reviews.store');

    Route::get('/reviews/{review}/edit', function () {
        return 'Review Edit Page (Dummy)';
    })->name('reviews.edit');

    Route::put('/reviews/{review}', function () {
        return back();
    })->name('reviews.update');

    Route::delete('/reviews/{review}', function () {
        return back();
    })->name('reviews.destroy');

    // レビューいいね
    Route::post('/reviews/{review}/like', function () {
        return back();
    })->name('reviews.like');
});

// ２. 誰でもアクセス可能なルート（ゲスト可）
// 書籍一覧
Route::get('/', [BookController::class, 'index'])->name('books.index');
// 書籍一覧（/booksでも表示される）・書籍詳細
Route::resource('books', BookController::class)->only([
    'index',
    'show',
]);

// ランキング（仮）
Route::get('/ranking', function () {
    return 'Ranking Page (Dummy)';
})->name('ranking.index');

