<?php

use App\Http\Controllers\BookController;
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

// 機能確認用暫定トップページ
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