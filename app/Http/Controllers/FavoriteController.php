<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り書籍一覧を表示
     */
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りの追加 / 解除（トグル）
     */
    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $request->user()->favoriteBooks()->toggle($book->id);

        return back();
    }
}
