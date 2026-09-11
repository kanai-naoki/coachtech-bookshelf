<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * ジャンル一覧画面の表示
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')->latest()->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンル詳細画面の表示
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()->latest()->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * ジャンル新規登録画面の表示
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルの新規登録処理
     */
    public function store(GenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated() + [
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを作成しました。');
    }

    /**
     * ジャンル編集画面の表示
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンルの更新処理
     */
    public function update(GenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンルの削除処理
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        // 紐づく書籍が存在する場合は削除を制限
        if ($genre->books()->exists()) {
            return redirect()
                ->route('genres.index')
                ->with('error', '書籍が登録されているジャンルは削除できません。');
        }

        $genre->delete();

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
