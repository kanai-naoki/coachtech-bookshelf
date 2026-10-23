<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * レビュー投稿処理。
     *
     * @param ReviewRequest $request 検証済みのレビュー入力
     * @param Book $book 対象書籍
     * @return RedirectResponse
     */
    public function store(ReviewRequest $request, Book $book): RedirectResponse
    {
        $book->reviews()->create(
            $request->safe()->only(['rating', 'comment']) + ['user_id' => $request->user()->id]
        );

        return redirect()
            ->route('books.show', $book);
    }

    /**
     * レビュー編集画面表示（投稿者本人のみ）。
     *
     * @param Review $review 編集対象のレビュー
     * @return View
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    /**
     * レビュー更新処理（投稿者本人のみ）。
     *
     * @param ReviewRequest $request 検証済みのレビュー入力
     * @param Review $review 更新対象のレビュー
     * @return RedirectResponse
     */
    public function update(ReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()
            ->route('books.show', $review->book_id)
            ->with('status', 'レビューを更新しました。');
    }

    /**
     * レビュー削除処理（投稿者本人のみ）。
     *
     * @param Review $review 削除対象のレビュー
     * @return RedirectResponse
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        DB::transaction(function () use ($review): void {
            $review->likedByUsers()->detach();
            $review->delete();
        });

        return redirect()
            ->route('books.index')
            ->with('success', 'レビューを削除しました。');
    }
}
