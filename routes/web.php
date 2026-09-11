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

// 書籍一覧（トップページ）：（/）と（/books）の両方で表示
// Route::get('/', [BookController::class, 'index'])->name('books.index');
// Route::get('/books', [BookController::class, 'index']);

// 書籍詳細
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');



Route::middleware(['auth'])->group(function () {
    // ジャンル一覧・詳細・編集（追加・削除）画面
    Route::resource('genres', GenreController::class);
});

// １．機能確認用暫定トップページ
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user()->name;
        $csrf = csrf_field();
        $logoutUrl = route('logout');

        return "
            <div style='padding: 20px; font-family: sans-serif;'>
                <h2>トップページ（仮）</h2>
                <p>ログイン中: <strong>{$user}</strong></p>
                <form method='POST' action='{$logoutUrl}'>
                    {$csrf}
                    <button type='submit'>ログアウト</button>
                </form>
            </div>
        ";
    }

    return "
        <div style='padding: 20px; font-family: sans-serif;'>
            <h2>トップページ（仮）</h2>
            <p>ログインしていません。</p>
            <a href='" . route('login') . "'>ログイン</a> |
            <a href='" . route('register') . "'>会員登録</a>
        </div>
    ";
})->name('top');

// 2. 未実装機能の仮ルート設定（エラー防止用）
Route::get('/books', fn() => '書籍一覧（開発中）')->name('books.index');
Route::get('/books/create', fn() => '書籍登録（開発中）')->name('books.create');
Route::get('/ranking', fn() => 'ランキング（開発中）')->name('ranking.index');
Route::get('/favorites', fn() => 'お気に入り（開発中）')->name('favorites.index');