<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューを更新できるか判定する（投稿者本人のみ）。
     *
     * @param User $user 操作ユーザー
     * @param Review $review 対象のレビュー
     * @return bool
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * レビューを削除できるか判定する（投稿者本人のみ）。
     *
     * @param User $user 操作ユーザー
     * @param Review $review 対象のレビュー
     * @return bool
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
