<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // 書籍一覧画面（トップページ)
    public function index()
    {
        // 10件/ページでページネーションし、ジャンル情報も取得
        $books = Book::with('genres')->latest()->paginate(10);

        return view('books.index', compact('books'));
    }

    // 書籍詳細画面
    public function show(Book $book)
    {
        // ジャンルと、レビュー（投稿ユーザー情報 ＋ いいね件数）を一括取得
        $book->load([
            'genres',
            'reviews' => function ($query) {
                $query->with('user')->withCount('likedByUsers');
            }
        ]);

        return view('books.show', compact('book'));
    }
}
