<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧画面（トップページ）の表示
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        $books = Book::with('genres')
            ->latest()
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 書籍詳細画面の表示
     *
     * @param  \App\Models\Book  $book
     * @return \Illuminate\View\View
     */
    public function show(Book $book): View
    {
        // ジャンル、レビュー（投稿ユーザー情報 ＋ いいね件数）を一括取得
        $book->load([
            'genres',
            'reviews' => function ($query) {
                $query->with('user')->withCount('likedByUsers');
            },
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍新規登録画面の表示
     *
     * @return \Illuminate\View\View
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍新規登録処理
     *
     * @param  \App\Http\Requests\BookRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(BookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $book = $user->books()->create($request->validated());
            $book->genres()->sync($request->validated('genres'));
            return $book;
        });

        return redirect()->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍編集画面の表示
     *
     * @param  \App\Models\Book  $book
     * @return \Illuminate\View\View
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍更新処理
     */
    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        DB::transaction(function () use ($request, $book) {
            $book->update($request->validated());
            $book->genres()->sync($request->validated('genres'));
        });

        return redirect()->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍削除処理
     *
     * @param  \App\Models\Book  $book
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        DB::transaction(function () use ($book) {
            $book->genres()->detach();
            $book->delete();
        });

        return redirect()->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}