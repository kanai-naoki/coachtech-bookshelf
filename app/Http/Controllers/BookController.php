<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧画面（トップページ）の表示。
     * キーワード検索・ジャンル絞り込み・並び替え・ページネーションに対応する。
     *
     * @param  Request  $request  keyword / genre / sort を受け取るリクエスト
     */
    public function index(Request $request): View
    {
        $books = Book::with(['genres', 'reviews'])
            ->search($request->input('keyword'))
            ->inGenre($request->input('genre'))
            ->sortedBy((string) $request->input('sort', 'latest'))
            ->paginate(10)
            ->appends($request->query());

        $genres = Genre::all();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 書籍詳細画面の表示（ジャンル、投稿者・いいね件数付きレビューを一括取得）。
     *
     * @param  Book  $book  ルートモデルバインディングされた書籍
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
     * 書籍新規登録画面の表示。
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍新規登録処理。書籍とジャンルの紐付けを1トランザクションで行う。
     *
     * @param  BookRequest  $request  検証済みの書籍入力
     * @return RedirectResponse 登録した書籍の詳細画面へのリダイレクト
     */
    public function store(BookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request): Book {
            $book = $request->user()->books()->create($request->safe()->except('genres'));
            $book->genres()->sync($request->validated('genres'));

            return $book;
        });

        return redirect()->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍編集画面の表示（登録者本人のみ）。
     *
     * @param  Book  $book  編集対象の書籍
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍更新処理（登録者本人のみ）。書籍とジャンルの更新を1トランザクションで行う。
     *
     * @param  BookRequest  $request  検証済みの書籍入力
     * @param  Book  $book  更新対象の書籍
     */
    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        DB::transaction(function () use ($request, $book): void {
            $book->update($request->safe()->except('genres'));
            $book->genres()->sync($request->validated('genres'));
        });

        return redirect()->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍削除処理（登録者本人のみ）。ジャンル紐付けの解除と削除を1トランザクションで行う。
     *
     * @param  Book  $book  削除対象の書籍
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        DB::transaction(function () use ($book): void {
            $book->genres()->detach();
            $book->delete();
        });

        return redirect()->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
