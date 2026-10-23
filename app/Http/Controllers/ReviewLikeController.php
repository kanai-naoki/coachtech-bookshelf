<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * レビューへの「いいね」を追加/解除（トグル）する。
     *
     * @param Request $request 認証済みユーザーを含むリクエスト
     * @param Review $review 対象レビュー
     * @return RedirectResponse
     */
    public function __invoke(Request $request, Review $review): RedirectResponse
    {
        $review->likedByUsers()->toggle($request->user()->id);

        return back();
    }
}
