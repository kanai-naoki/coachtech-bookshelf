<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り書籍一覧を表示する。
     *
     * @param Request $request 認証済みユーザーを含むリクエスト
     * @return View
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りの追加 / 解除（トグル）を行う。
     *
     * @param Request $request 認証済みユーザーを含むリクエスト
     * @param Book $book 対象書籍
     * @return RedirectResponse
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $request->user()->favoriteBooks()->toggle($book->id);

        return back();
    }
}
